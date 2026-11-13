<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StageSyncRequest;
use App\Http\Resources\EventResource;
use App\Http\Resources\RoundResource;
use App\Http\Resources\RoundResultResource;
use App\Http\Responses\ApiResponse;
use App\Models\Event;
use App\Models\User;
use App\Services\AdminTabulationService;
use Illuminate\Http\JsonResponse;

final class StageSyncController extends Controller
{
    public function push(StageSyncRequest $request, AdminTabulationService $service): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();
        $event = Event::findOrFail($validated['event_id']);

        $result = $service->syncStage(
            actor: $actor,
            event: $event,
            payload: $validated,
            ipAddress: $request->ip() ?? '127.0.0.1',
        );

        return ApiResponse::success([
            'event' => new EventResource($result['event']),
            'active_round' => $result['active_round'] ? new RoundResource($result['active_round']) : null,
            'leaderboard' => RoundResultResource::collection($result['leaderboard']),
        ], 'Stage state synchronized.');
    }
}
