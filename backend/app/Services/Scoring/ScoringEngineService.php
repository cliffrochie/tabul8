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

        $cumulativeMap = $this->computeCumulativeScoresIfApplicable($round, $dtos);

        return DB::transaction(function () use ($round, $dtos, $cumulativeMap): Collection {
            $now = now();
            $persisted = collect();

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
                        'cumulative_score' => $cumulativeMap[$dto->contestantId] ?? null,
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
     * Combines 0-100 percentage-scale net_composite across cumulative rounds up to $round->sequence.
     *
     * @param  Collection<int, RoundResultDTO>  $currentDtos
     * @return array<int, float>
     */
    private function computeCumulativeScoresIfApplicable(Round $round, Collection $currentDtos): array
    {
        if ($round->is_back_to_zero) {
            return [];
        }

        $priorRounds = $round->event->rounds()
            ->where('sequence', '<', $round->sequence)
            ->where('is_back_to_zero', false)
            ->with('results')
            ->get();

        if ($priorRounds->isEmpty()) {
            return [];
        }

        $priorByRoundContestant = [];
        foreach ($priorRounds as $prior) {
            foreach ($prior->results as $res) {
                $priorByRoundContestant[$prior->id][$res->contestant_id] = (float) $res->net_composite;
            }
        }

        $cumulative = [];
        foreach ($currentDtos as $dto) {
            $sum = round($dto->netComposite * ((float) $round->weight_in_overall / 100.0), 3, PHP_ROUND_HALF_UP);

            foreach ($priorRounds as $prior) {
                $priorNet = $priorByRoundContestant[$prior->id][$dto->contestantId] ?? 0.0;
                $sum += round($priorNet * ((float) $prior->weight_in_overall / 100.0), 3, PHP_ROUND_HALF_UP);
            }

            $cumulative[$dto->contestantId] = round($sum, 3, PHP_ROUND_HALF_UP);
        }

        return $cumulative;
    }
}
