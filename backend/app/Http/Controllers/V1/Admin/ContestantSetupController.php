<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportContestantsCsvRequest;
use App\Http\Requests\Admin\StoreContestantRequest;
use App\Http\Resources\ContestantResource;
use App\Http\Responses\ApiResponse;
use App\Models\Contestant;
use App\Models\Event;
use App\Models\Score;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ContestantSetupController extends Controller
{
    public function store(StoreContestantRequest $request, Event $event): JsonResponse
    {
        $data = $request->validated();
        $nextNumber = ($event->contestants()->max('candidate_number') ?? 0) + 1;

        $contestant = Contestant::create([
            'event_id' => $event->id,
            'candidate_number' => $data['candidate_number'] ?? $nextNumber,
            'name' => $data['name'],
            'origin_or_group' => $data['origin_or_group'],
            'performance_meta' => $data['performance_meta'] ?? null,
            'category' => $data['category'] ?? 'Solo',
            'asset_ratio' => $data['asset_ratio'] ?? 'PORTRAIT_4:5',
            'status' => $data['status'] ?? 'active',
        ]);

        return ApiResponse::success(
            ['contestant' => new ContestantResource($contestant)],
            'Contestant added.',
            201
        );
    }

    public function update(StoreContestantRequest $request, Contestant $contestant): JsonResponse
    {
        $contestant->update($request->validated());

        return ApiResponse::success(
            ['contestant' => new ContestantResource($contestant->fresh())],
            'Contestant updated.'
        );
    }

    public function destroy(Request $request, Contestant $contestant): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User || ! $actor->is_active || ! in_array($actor->role, ['super_admin', 'tabulator'], true)) {
            return ApiResponse::error('Forbidden.', null, 403);
        }

        if (Score::where('contestant_id', $contestant->id)->where('status', 'LOCKED')->exists()) {
            throw new ConflictHttpException('Cannot delete a contestant with locked judge scores; mark as withdrawn instead.');
        }

        if ($contestant->photo_path) {
            Storage::disk('local')->delete($contestant->photo_path);
        }

        $contestant->delete();

        return ApiResponse::success(null, 'Contestant removed.');
    }

    public function importCsv(ImportContestantsCsvRequest $request, Event $event): JsonResponse
    {
        $rawCsv = $request->hasFile('file')
            ? (string) file_get_contents($request->file('file')->getRealPath())
            : (string) $request->input('csv_text', '');

        $lines = array_values(array_filter(
            preg_split("/\r\n|\n|\r/", trim($rawCsv)) ?: [],
            fn (string $l): bool => trim($l) !== ''
        ));

        if ($lines === []) {
            return ApiResponse::error('CSV contains no rows.', ['file' => ['CSV contains no rows.']], 422);
        }

        $firstCols = str_getcsv($lines[0]);
        $hasHeader = isset($firstCols[1]) && str_contains(strtolower($firstCols[1]), 'name');
        $startIdx = $hasHeader ? 1 : 0;

        $created = [];
        DB::transaction(function () use ($event, $lines, $startIdx, &$created): void {
            $nextAutoNum = ($event->contestants()->max('candidate_number') ?? 0) + 1;

            for ($i = $startIdx; $i < count($lines); $i++) {
                $cols = str_getcsv($lines[$i]);
                $name = trim((string) ($cols[1] ?? $cols[0] ?? ''));
                if ($name === '') {
                    continue;
                }

                $numRaw = trim((string) ($cols[0] ?? ''));
                $candidateNumber = (ctype_digit($numRaw) && (int) $numRaw > 0)
                    ? (int) $numRaw
                    : $nextAutoNum++;

                $origin = trim((string) ($cols[2] ?? '')) ?: 'Independent';
                $category = trim((string) ($cols[3] ?? '')) ?: 'Solo';
                $meta = trim((string) ($cols[4] ?? '')) ?: null;

                $contestant = Contestant::updateOrCreate(
                    [
                        'event_id' => $event->id,
                        'candidate_number' => $candidateNumber,
                        'category' => $category,
                    ],
                    [
                        'name' => $name,
                        'origin_or_group' => $origin,
                        'performance_meta' => $meta,
                        'status' => 'active',
                    ]
                );
                $created[] = $contestant;
            }
        });

        return ApiResponse::success([
            'imported_count' => count($created),
            'contestants' => ContestantResource::collection(collect($created)),
        ], count($created).' contestant(s) imported.');
    }

    public function renumber(Request $request, Event $event): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User || ! $actor->is_active || ! in_array($actor->role, ['super_admin', 'tabulator'], true)) {
            return ApiResponse::error('Forbidden.', null, 403);
        }

        $contestants = DB::transaction(function () use ($event) {
            $groups = $event->contestants()->orderBy('candidate_number')->orderBy('id')->get()->groupBy('category');

            foreach ($groups as $categoryRows) {
                foreach ($categoryRows as $idx => $c) {
                    $c->update(['candidate_number' => 100000 + $idx]);
                }
                foreach ($categoryRows as $idx => $c) {
                    $c->update(['candidate_number' => $idx + 1]);
                }
            }

            return $event->contestants()->orderBy('candidate_number')->get();
        });

        return ApiResponse::success(
            ['contestants' => ContestantResource::collection($contestants)],
            'Contestants renumbered.'
        );
    }

    public function uploadPhoto(Request $request, Contestant $contestant): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User || ! $actor->is_active || ! in_array($actor->role, ['super_admin', 'tabulator'], true)) {
            return ApiResponse::error('Forbidden.', null, 403);
        }

        $request->validate([
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($contestant->photo_path) {
            Storage::disk('local')->delete($contestant->photo_path);
        }

        $path = $request->file('photo')->store("contestants/{$contestant->event_id}", 'local');
        $contestant->update(['photo_path' => $path]);

        return ApiResponse::success(
            ['contestant' => new ContestantResource($contestant->fresh())],
            'Contestant portrait uploaded.'
        );
    }

    public function showPhoto(Event $event, Contestant $contestant): StreamedResponse
    {
        if ((int) $contestant->event_id !== (int) $event->id) {
            abort(404);
        }

        if (! $contestant->photo_path || ! Storage::disk('local')->exists($contestant->photo_path)) {
            abort(404);
        }

        return Storage::disk('local')->response($contestant->photo_path);
    }
}
