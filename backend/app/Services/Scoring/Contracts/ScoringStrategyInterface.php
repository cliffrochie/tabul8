<?php

namespace App\Services\Scoring\Contracts;

use App\Models\Round;
use App\Services\Scoring\DTOs\RoundResultDTO;
use Illuminate\Support\Collection;

interface ScoringStrategyInterface
{
    /**
     * Compute round results for all active contestants in the given round.
     *
     * @return Collection<int, RoundResultDTO>
     */
    public function calculate(Round $round): Collection;
}
