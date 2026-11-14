<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\Judge;
use App\Models\User;

final class EventPolicy
{
    public function viewState(User|Judge $actor, Event $event): bool
    {
        if ($actor instanceof User) {
            return (bool) $actor->is_active;
        }

        if ($actor instanceof Judge) {
            return (bool) $actor->is_active && (int) $actor->event_id === (int) $event->id;
        }

        return false;
    }
}
