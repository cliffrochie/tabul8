<?php

namespace App\Policies;

use App\Models\Judge;
use App\Models\Round;
use App\Models\User;

final class ScorePolicy
{
    public function mutateRound(User|Judge $actor, Round $round): bool
    {
        return $actor instanceof Judge
            && $actor->is_active
            && (int) $actor->event_id === (int) $round->event_id;
    }
}
