<?php

namespace App\Policies;

use App\Models\Judge;
use App\Models\Round;
use App\Models\User;

final class RoundPolicy
{
    public function exportTally(User|Judge $actor, Round $round): bool
    {
        return $actor instanceof User
            && $actor->is_active
            && in_array($actor->role, ['super_admin', 'tabulator', 'auditor'], true);
    }
}
