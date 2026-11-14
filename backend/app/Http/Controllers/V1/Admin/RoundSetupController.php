<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GenerateFunnelRequest;
use App\Http\Requests\Admin\ReorderRoundsRequest;
use App\Http\Requests\Admin\StoreRoundRequest;
use App\Http\Resources\RoundResource;
use App\Http\Responses\ApiResponse;
use App\Models\Event;
use App\Models\Round;
use App\Models\User;
use App\Services\EventSetupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RoundSetupController extends Controller
{
    public function store(StoreRoundRequest $request, Event $event, EventSetupService $service): JsonResponse
    {
        $round = $service->createRound($event, $request->validated());

        return ApiResponse::success(
            ['round' => new RoundResource($round)],
            'Round added.',
            201
        );
    }

    public function update(StoreRoundRequest $request, Round $round, EventSetupService $service): JsonResponse
    {
        $updated = $service->updateRound($round, $request->validated());

        return ApiResponse::success(
            ['round' => new RoundResource($updated)],
            'Round updated.'
        );
    }

    public function destroy(Request $request, Round $round, EventSetupService $service): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User || ! $actor->is_active || ! in_array($actor->role, ['super_admin', 'tabulator'], true)) {
            return ApiResponse::error('Forbidden.', null, 403);
        }

        $service->deleteRound($round);

        return ApiResponse::success(null, 'Round deleted.');
    }

    public function generateFunnel(GenerateFunnelRequest $request, Event $event, EventSetupService $service): JsonResponse
    {
        $v = $request->validated();
        $rounds = $service->generateFunnel(
            event: $event,
            roundCount: (int) $v['round_count'],
            finalWinnersCount: (int) $v['final_winners_count'],
            finalBackToZero: (bool) ($v['final_back_to_zero'] ?? true),
        );

        return ApiResponse::success(
            ['rounds' => RoundResource::collection($rounds)],
            'Multi-round elimination funnel generated.'
        );
    }

    public function reorder(ReorderRoundsRequest $request, Event $event, EventSetupService $service): JsonResponse
    {
        $rounds = $service->reorderRounds($event, $request->validated('round_ids'));

        return ApiResponse::success(
            ['rounds' => RoundResource::collection($rounds)],
            'Rounds reordered.'
        );
    }
}
