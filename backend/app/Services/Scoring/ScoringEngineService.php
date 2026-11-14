<?php

namespace App\Services\Scoring;

use App\Models\Round;
use App\Models\RoundResult;
use App\Services\Scoring\Contracts\ScoringStrategyInterface;
use App\Services\Scoring\DTOs\RoundResultDTO;
use App\Services\Scoring\Strategies\OrdinalScoringStrategy;
use App\Services\Scoring\Strategies\TrimmedMeanScoringStrategy;
use App\Services\Scoring\Strategies\WeightedScoringStrategy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ScoringEngineService
{
    public function resolveStrategy(string $strategy): ScoringStrategyInterface
    {
        return match ($strategy) {
            'weighted' => new WeightedScoringStrategy,
            'ordinal' => new OrdinalScoringStrategy,
            'trimmed' => new TrimmedMeanScoringStrategy,
            default => throw new InvalidArgumentException("Unsupported scoring strategy: {$strategy}"),
        };
    }

    /**
     * Compute and persist materialized round_results inside a transaction.
     *
     * @return Collection<int, RoundResult>
     */
    public function tabulateRound(Round $round): Collection
    {
        $strategy = $this->resolveStrategy($round->scoring_strategy);
        $dtos = $strategy->calculate($round);

        if ($dtos->isEmpty()) {
            return collect();
        }

        $dtos = $this->applySegmentedCumulativeRanking($round, $dtos);

        return DB::transaction(function () use ($round, $dtos): Collection {
            $now = now();
            $persisted = collect();
            $activeIds = $dtos->pluck('contestantId')->all();

            // Remove stale RoundResult rows for contestants not in this round's active roster
            RoundResult::where('round_id', $round->id)
                ->whereNotIn('contestant_id', $activeIds)
                ->delete();

            foreach ($dtos as $dto) {
                $record = RoundResult::updateOrCreate(
                    [
                        'round_id' => $round->id,
                        'contestant_id' => $dto->contestantId,
                    ],
                    [
                        'mean_score' => $dto->meanScore,
                        'trimmed_mean' => $dto->trimmedMean,
                        'rank_sum' => $dto->rankSum,
                        'first_place_count' => $dto->firstPlaceCount,
                        'deduction_points' => $dto->deductionPoints,
                        'net_composite' => $dto->netComposite,
                        'cumulative_score' => $dto->cumulativeScore,
                        'primary_crit_avg' => $dto->primaryCritAvg,
                        'chairman_rank' => $dto->chairmanRank,
                        'final_rank' => $dto->finalRank,
                        'is_tied' => $dto->isTied,
                        'tie_break_rule_applied' => $dto->tieBreakRuleApplied,
                        'is_qualified' => $dto->isQualified,
                        'judge_breakdown_snapshot' => $dto->judgeBreakdownSnapshot,
                        'computed_at' => $now,
                    ]
                );

                $persisted->push($record);
            }

            return $persisted->sortBy('final_rank')->values();
        });
    }

    /**
     * Returns true when an unbroken tie straddles the Top N advancement cutoff boundary.
     *
     * @param  Collection<int, RoundResult>  $results
     */
    public function hasUnresolvedCutoffTie(Round $round, Collection $results): bool
    {
        $k = (int) $round->advancement_count;
        if ($k <= 0 || $results->count() <= $k) {
            return false;
        }

        $ordered = $results->sortBy('final_rank')->values();
        $atCutoff = $ordered->get($k - 1);
        $afterCutoff = $ordered->get($k);

        if ($atCutoff === null || $afterCutoff === null) {
            return false;
        }

        return (int) $atCutoff->final_rank === (int) $afterCutoff->final_rank;
    }

    /**
     * Resolves the contiguous cumulative segment bounded by the most recent is_back_to_zero = true reset
     * and re-ranks contestants by normalized cumulative score when segment length >= 2.
     *
     * @param  Collection<int, RoundResultDTO>  $currentDtos
     * @return Collection<int, RoundResultDTO>
     */
    private function applySegmentedCumulativeRanking(Round $round, Collection $currentDtos): Collection
    {
        if ($round->is_back_to_zero) {
            return $currentDtos;
        }

        $allUpToCurrent = $round->event->rounds()
            ->where('sequence', '<=', $round->sequence)
            ->orderBy('sequence')
            ->with('results')
            ->get();

        // Walk backwards to find segment start s_r (most recent is_back_to_zero = true, or first round)
        $segmentRounds = collect();
        foreach ($allUpToCurrent->reverse() as $candidate) {
            $segmentRounds->prepend($candidate);
            if ($candidate->is_back_to_zero) {
                break;
            }
        }

        if ($segmentRounds->count() < 2) {
            return $currentDtos;
        }

        $totalWeight = $segmentRounds->sum(fn (Round $r): float => (float) $r->weight_in_overall);
        if ($totalWeight <= 0.0) {
            return $currentDtos;
        }

        $priorByRoundContestant = [];
        foreach ($segmentRounds as $segRound) {
            if ($segRound->id === $round->id) {
                continue;
            }
            foreach ($segRound->results as $res) {
                $priorByRoundContestant[$segRound->id][$res->contestant_id] = (float) $res->net_composite;
            }
        }

        $withCumulative = $currentDtos->map(function (RoundResultDTO $dto) use ($round, $segmentRounds, $priorByRoundContestant, $totalWeight): RoundResultDTO {
            $weightedSum = $dto->netComposite * (float) $round->weight_in_overall;

            foreach ($segmentRounds as $segRound) {
                if ($segRound->id === $round->id) {
                    continue;
                }
                $priorNet = $priorByRoundContestant[$segRound->id][$dto->contestantId] ?? 0.0;
                $weightedSum += $priorNet * (float) $segRound->weight_in_overall;
            }

            $cumulative = round($weightedSum / $totalWeight, 3, PHP_ROUND_HALF_UP);

            return new RoundResultDTO(
                roundId: $dto->roundId,
                contestantId: $dto->contestantId,
                meanScore: $dto->meanScore,
                trimmedMean: $dto->trimmedMean,
                rankSum: $dto->rankSum,
                firstPlaceCount: $dto->firstPlaceCount,
                deductionPoints: $dto->deductionPoints,
                netComposite: $dto->netComposite,
                cumulativeScore: $cumulative,
                primaryCritAvg: $dto->primaryCritAvg,
                chairmanRank: $dto->chairmanRank,
                finalRank: $dto->finalRank,
                isTied: $dto->isTied,
                tieBreakRuleApplied: $dto->tieBreakRuleApplied,
                isQualified: $dto->isQualified,
                judgeBreakdownSnapshot: $dto->judgeBreakdownSnapshot,
            );
        })->all();

        usort($withCumulative, function (RoundResultDTO $a, RoundResultDTO $b): int {
            $dispCmp = round((float) $b->cumulativeScore, 2, PHP_ROUND_HALF_UP)
                <=> round((float) $a->cumulativeScore, 2, PHP_ROUND_HALF_UP);
            if ($dispCmp !== 0) {
                return $dispCmp;
            }

            $unroundedCmp = ($b->cumulativeScore ?? 0.0) <=> ($a->cumulativeScore ?? 0.0);
            if ($unroundedCmp !== 0) {
                return $unroundedCmp;
            }

            $netCmp = $b->netComposite <=> $a->netComposite;
            if ($netCmp !== 0) {
                return $netCmp;
            }

            return $b->primaryCritAvg <=> $a->primaryCritAvg;
        });

        $reranked = [];
        $currentRank = 1;
        $total = count($withCumulative);
        for ($idx = 0; $idx < $total; $idx++) {
            $dto = $withCumulative[$idx];
            $prev = $idx > 0 ? $withCumulative[$idx - 1] : null;
            $next = $idx + 1 < $total ? $withCumulative[$idx + 1] : null;

            $samePrev = $prev !== null
                && $prev->cumulativeScore === $dto->cumulativeScore
                && $prev->netComposite === $dto->netComposite
                && $prev->primaryCritAvg === $dto->primaryCritAvg;
            $sameNext = $next !== null
                && $dto->cumulativeScore === $next->cumulativeScore
                && $dto->netComposite === $next->netComposite
                && $dto->primaryCritAvg === $next->primaryCritAvg;

            if ($idx > 0 && ! $samePrev) {
                $currentRank = $idx + 1;
            }

            $reranked[] = new RoundResultDTO(
                roundId: $dto->roundId,
                contestantId: $dto->contestantId,
                meanScore: $dto->meanScore,
                trimmedMean: $dto->trimmedMean,
                rankSum: $dto->rankSum,
                firstPlaceCount: $dto->firstPlaceCount,
                deductionPoints: $dto->deductionPoints,
                netComposite: $dto->netComposite,
                cumulativeScore: $dto->cumulativeScore,
                primaryCritAvg: $dto->primaryCritAvg,
                chairmanRank: $dto->chairmanRank,
                finalRank: $currentRank,
                isTied: $samePrev || $sameNext,
                tieBreakRuleApplied: $dto->tieBreakRuleApplied,
                isQualified: $currentRank <= $round->advancement_count,
                judgeBreakdownSnapshot: $dto->judgeBreakdownSnapshot,
            );
        }

        return collect($reranked);
    }
}
