<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEventRequest;
use App\Http\Resources\EventCollection;
use App\Http\Resources\EventResource;
use App\Http\Responses\ApiResponse;
use App\Models\Event;
use App\Models\User;
use App\Services\EventSetupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EventCrudController extends Controller
{
    public function index(Request $request): JsonResponse|EventCollection
    {
        $actor = $request->user();
        if (! $actor instanceof User || ! $actor->is_active || ! in_array($actor->role, ['super_admin', 'tabulator', 'mc_stage', 'auditor'], true)) {
            return ApiResponse::error('Forbidden.', null, 403);
        }

        $perPage = min(50, max(1, (int) $request->integer('per_page', 15)));
        $events = Event::orderByDesc('id')->paginate($perPage);

        return new EventCollection($events);
    }

    public function store(StoreEventRequest $request, EventSetupService $service): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $event = $service->createEvent($actor, $request->validated());

        return ApiResponse::success(
            ['event' => new EventResource($event)],
            'Event created.',
            201
        );
    }

    public function update(StoreEventRequest $request, Event $event, EventSetupService $service): JsonResponse
    {
        $updated = $service->updateEvent($event, $request->validated());

        return ApiResponse::success(
            ['event' => new EventResource($updated)],
            'Event updated.'
        );
    }

    public function destroy(Request $request, Event $event, EventSetupService $service): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User || ! $actor->is_active || ! in_array($actor->role, ['super_admin', 'tabulator'], true)) {
            return ApiResponse::error('Forbidden.', null, 403);
        }

        $service->deleteEvent($event);

        return ApiResponse::success(null, 'Event deleted.');
    }

    public function preflight(Request $request, Event $event, EventSetupService $service): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User || ! $actor->is_active || ! in_array($actor->role, ['super_admin', 'tabulator', 'mc_stage'], true)) {
            return ApiResponse::error('Forbidden.', null, 403);
        }

        return ApiResponse::success(
            $service->evaluatePreFlight($event),
            'Pre-flight checklist evaluated.'
        );
    }
}
