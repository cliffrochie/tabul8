<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreJudgeRequest;
use App\Http\Resources\JudgeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Event;
use App\Models\Judge;
use App\Models\Score;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class JudgeSetupController extends Controller
{
    public function store(StoreJudgeRequest $request, Event $event): JsonResponse
    {
        $data = $request->validated();
        $nextNumber = ($event->judges()->max('judge_number') ?? 0) + 1;
        $plainPin = $this->generateUniquePin($event);

        $judge = DB::transaction(function () use ($event, $data, $nextNumber, $plainPin): Judge {
            $isChairman = (bool) ($data['is_chairman'] ?? false);
            if ($isChairman) {
                Judge::where('event_id', $event->id)->update(['is_chairman' => false]);
            }

            return Judge::create([
                'event_id' => $event->id,
                'judge_number' => $data['judge_number'] ?? $nextNumber,
                'name' => $data['name'],
                'role_title' => $data['role_title'] ?? 'Adjudicator',
                'access_pin_hash' => Hash::make($plainPin),
                'plain_pin_reference' => $plainPin,
                'is_chairman' => $isChairman,
                'is_active' => $data['is_active'] ?? true,
            ]);
        });

        return ApiResponse::success(
            [
                'judge' => new JudgeResource($judge),
                'plain_pin' => $plainPin,
            ],
            'Judge added.',
            201
        );
    }

    public function update(StoreJudgeRequest $request, Judge $judge): JsonResponse
    {
        $data = $request->validated();

        $updated = DB::transaction(function () use ($judge, $data): Judge {
            if (! empty($data['is_chairman'])) {
                Judge::where('event_id', $judge->event_id)
                    ->where('id', '!=', $judge->id)
                    ->update(['is_chairman' => false]);
            }

            $judge->update($data);

            return $judge->fresh();
        });

        return ApiResponse::success(
            ['judge' => new JudgeResource($updated)],
            'Judge updated.'
        );
    }

    public function destroy(Request $request, Judge $judge): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User || ! $actor->is_active || ! in_array($actor->role, ['super_admin', 'tabulator'], true)) {
            return ApiResponse::error('Forbidden.', null, 403);
        }

        if (Score::where('judge_id', $judge->id)->where('status', 'LOCKED')->exists()) {
            throw new ConflictHttpException('Cannot remove a judge with locked scores; deactivate instead.');
        }

        $judge->tokens()->delete();
        $judge->delete();

        return ApiResponse::success(null, 'Judge removed.');
    }

    public function regeneratePin(Request $request, Judge $judge): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User || ! $actor->is_active || ! in_array($actor->role, ['super_admin', 'tabulator'], true)) {
            return ApiResponse::error('Forbidden.', null, 403);
        }

        $newPin = $this->generateUniquePin($judge->event, $judge->id);

        DB::transaction(function () use ($judge, $newPin): void {
            $judge->update([
                'access_pin_hash' => Hash::make($newPin),
                'plain_pin_reference' => $newPin,
            ]);
            $judge->tokens()->delete();
        });

        return ApiResponse::success(
            [
                'judge' => new JudgeResource($judge->fresh()),
                'plain_pin' => $newPin,
            ],
            'Judge PIN regenerated.'
        );
    }

    public function exportJudgeCardsPdf(Request $request, Event $event): Response
    {
        $actor = $request->user();
        if (! $actor instanceof User || ! $actor->is_active || ! in_array($actor->role, ['super_admin', 'tabulator'], true)) {
            abort(403, 'Only authorized tabulation staff can export judge credential cards.');
        }

        $judges = $event->judges()->orderBy('judge_number')->get();

        $pdf = Pdf::loadView('pdf.judge-cards', [
            'event' => $event,
            'judges' => $judges,
            'generatedAt' => now()->format('Y-m-d H:i:s T'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download("judge-tent-cards-event-{$event->id}.pdf");
    }

    private function generateUniquePin(Event $event, ?int $ignoreJudgeId = null): string
    {
        $existing = Judge::where('event_id', $event->id)
            ->when($ignoreJudgeId !== null, fn ($q) => $q->where('id', '!=', $ignoreJudgeId))
            ->pluck('plain_pin_reference')
            ->all();

        do {
            $pin = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        } while (in_array($pin, $existing, true));

        return $pin;
    }
}
