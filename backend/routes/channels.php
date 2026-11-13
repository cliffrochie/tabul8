<?php

use App\Models\Judge;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('event.{eventId}', function ($actor, int $eventId): array|bool {
    if ($actor instanceof Judge && $actor->event_id === $eventId && $actor->is_active) {
        return ['id' => "judge-{$actor->id}", 'name' => $actor->name, 'role' => 'judge'];
    }

    if ($actor instanceof User && $actor->is_active) {
        return ['id' => "user-{$actor->id}", 'name' => $actor->name, 'role' => $actor->role];
    }

    return false;
});

Broadcast::channel('tabulator.{eventId}', function ($actor, int $eventId): bool {
    return $actor instanceof User
        && $actor->is_active
        && in_array($actor->role, ['super_admin', 'tabulator', 'auditor'], true);
});

Broadcast::channel('judge.{judgeId}', function ($actor, int $judgeId): bool {
    return $actor instanceof Judge
        && $actor->id === $judgeId
        && $actor->is_active;
});
