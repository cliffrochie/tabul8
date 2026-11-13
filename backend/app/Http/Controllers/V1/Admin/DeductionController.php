<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDeductionRequest;
use App\Http\Resources\RoundResultResource;
use App\Http\Responses\ApiResponse;
use App\Models\Round;
use App\Models\User;
use App\Services\AdminTabulationService;
use Illuminate\Http\JsonResponse;

final class DeductionController extends Controller
{
    public function store(StoreDeductionRequest $request, AdminTabulationService $service): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();
        $round = Round::findOrFail($validated['round_id']);

        $leaderboard = $service->applyDeduction(
            actor: $actor,
            round: $round,
            contestantId: (int) $validated['contestant_id'],
            points: (float) $validated['points_deducted'],
            reason: $validated['reason'] ?? null,
            ipAddress: $request->ip() ?? '127.0.0.1',
        );

        return ApiResponse::success(
            ['leaderboard' => RoundResultResource::collection($leaderboard)],
            'Technical deduction updated.'
        );
    }
}
