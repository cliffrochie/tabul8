<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResolveUnlockRequest;
use App\Http\Resources\RoundResultResource;
use App\Http\Responses\ApiResponse;
use App\Models\Round;
use App\Models\User;
use App\Services\AdminTabulationService;
use Illuminate\Http\JsonResponse;

final class AdminScoreController extends Controller
{
    public function resolveUnlock(ResolveUnlockRequest $request, AdminTabulationService $service): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();
        $round = Round::findOrFail($validated['round_id']);

        $leaderboard = $service->resolveUnlock(
            actor: $actor,
            round: $round,
            contestantId: (int) $validated['contestant_id'],
            judgeId: (int) $validated['judge_id'],
            decision: $validated['decision'],
            reason: $validated['reason'] ?? null,
            ipAddress: $request->ip() ?? '127.0.0.1',
        );

        return ApiResponse::success(
            ['leaderboard' => RoundResultResource::collection($leaderboard)],
            "Unlock request {$validated['decision']}D."
        );
    }
}
