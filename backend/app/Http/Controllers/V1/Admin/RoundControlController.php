<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FinalizeRoundRequest;
use App\Http\Resources\RoundResultResource;
use App\Http\Responses\ApiResponse;
use App\Models\Judge;
use App\Models\Round;
use App\Models\User;
use App\Services\AdminTabulationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RoundControlController extends Controller
{
    public function finalize(FinalizeRoundRequest $request, Round $round, AdminTabulationService $service): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $leaderboard = $service->finalizeRound(
            actor: $actor,
            round: $round,
            ipAddress: $request->ip() ?? '127.0.0.1',
        );

        return ApiResponse::success(
            ['leaderboard' => RoundResultResource::collection($leaderboard)],
            'Round finalized and qualifiers advanced.'
        );
    }

    public function excludeJudge(Request $request, Round $round, Judge $judge, AdminTabulationService $service): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User || ! in_array($actor->role, ['super_admin', 'tabulator'], true)) {
            return ApiResponse::error('Forbidden.', null, 403);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $leaderboard = $service->excludeJudge(
            actor: $actor,
            round: $round,
            judge: $judge,
            reason: $validated['reason'],
            ipAddress: $request->ip() ?? '127.0.0.1',
        );

        return ApiResponse::success(
            ['leaderboard' => RoundResultResource::collection($leaderboard)],
            "Judge #{$judge->judge_number} excluded from active panel."
        );
    }
}
