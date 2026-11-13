<?php

namespace App\Services\Scoring\DTOs;

final readonly class RoundResultDTO
{
    /**
     * @param  array<int, array{judge_id: int, judge_number: int, score: float, rank: float, is_dropped: bool}>  $judgeBreakdownSnapshot
     */
    public function __construct(
        public int $roundId,
        public int $contestantId,
        public float $meanScore,
        public float $trimmedMean,
        public float $rankSum,
        public int $firstPlaceCount,
        public float $deductionPoints,
        public float $netComposite,
        public ?float $cumulativeScore,
        public float $primaryCritAvg,
        public ?float $chairmanRank,
        public int $finalRank,
        public bool $isTied,
        public string $tieBreakRuleApplied,
        public bool $isQualified,
        public array $judgeBreakdownSnapshot,
    ) {}
}
