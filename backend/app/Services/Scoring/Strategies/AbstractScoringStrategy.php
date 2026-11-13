<?php

namespace App\Services\Scoring\Strategies;

use App\Models\Contestant;
use App\Models\Criterion;
use App\Models\Judge;
use App\Models\Round;
use App\Services\Scoring\Contracts\ScoringStrategyInterface;
use App\Services\Scoring\DTOs\RoundResultDTO;
use Illuminate\Support\Collection;

abstract class AbstractScoringStrategy implements ScoringStrategyInterface
{
    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, RoundResultDTO>
     */
    abstract protected function rankAndFinalize(Round $round, Collection $rows): Collection;

    /**
     * @param  Collection<int, Judge>  $judges
     */
    protected function validateJudgePanel(Collection $judges): void
    {
        // Subclasses may enforce minimum judge counts (e.g. Trimmed Mean >= 5).
    }

    public function calculate(Round $round): Collection
    {
        $round->loadMissing(['event', 'criteria', 'deductions', 'scores']);

        $judges = $round->event->judges()
            ->where('is_active', true)
            ->orderBy('judge_number')
            ->get();

        $this->validateJudgePanel($judges);

        $contestants = $this->resolveActiveContestants($round);
        $leafCriteria = $this->resolveLeafCriteria($round);

        if ($contestants->isEmpty() || $judges->isEmpty() || $leafCriteria->isEmpty()) {
            return collect();
        }

        $deductBeforeRank = (bool) ($round->settings['deduct_before_judge_rank'] ?? false);
        $deductionsByContestant = $round->deductions->keyBy('contestant_id');
        $scoresIndexed = $this->indexScores($round);

        $judgeTotalsMatrix = [];
        foreach ($judges as $judge) {
            foreach ($contestants as $contestant) {
                $deduction = $deductBeforeRank
                    ? (float) ($deductionsByContestant->get($contestant->id)?->points_deducted ?? 0.0)
                    : 0.0;

                $rawTotal = $this->computeJudgeTotal(
                    $leafCriteria,
                    $scoresIndexed[$contestant->id][$judge->id] ?? []
                );

                $judgeTotalsMatrix[$judge->id][$contestant->id] = max(
                    0.0,
                    $this->round3($rawTotal - $deduction)
                );
            }
        }

        $perJudgeRanks = $this->computePerJudgeOrdinalRanks($judges, $contestants, $judgeTotalsMatrix);
        $primaryCriterion = $leafCriteria->first();
        $chairman = $judges->firstWhere('is_chairman', true);

        $rows = $contestants->map(function (Contestant $contestant) use (
            $judges,
            $judgeTotalsMatrix,
            $perJudgeRanks,
            $deductionsByContestant,
            $scoresIndexed,
            $primaryCriterion,
            $chairman
        ): array {
            $judgeScores = [];
            $rankSum = 0.0;
            $firstPlaceCount = 0;

            foreach ($judges as $judge) {
                $score = $judgeTotalsMatrix[$judge->id][$contestant->id];
                $rank = $perJudgeRanks[$judge->id][$contestant->id];
                $judgeScores[] = [
                    'judge_id' => $judge->id,
                    'judge_number' => $judge->judge_number,
                    'score' => $score,
                    'rank' => $rank,
                    'is_dropped' => false,
                ];
                $rankSum += $rank;
                if ($rank <= 1.5) {
                    $firstPlaceCount++;
                }
            }

            $rawScoreValues = array_column($judgeScores, 'score');
            $meanScore = $this->round3(array_sum($rawScoreValues) / count($rawScoreValues));

            $trimmedMean = $meanScore;
            if (count($rawScoreValues) >= 5) {
                $sorted = $rawScoreValues;
                sort($sorted, SORT_NUMERIC);
                $middle = array_slice($sorted, 1, count($sorted) - 2);
                $trimmedMean = $this->round3(array_sum($middle) / count($middle));
            }

            $deductionPoints = (float) ($deductionsByContestant->get($contestant->id)?->points_deducted ?? 0.0);

            $primarySum = 0.0;
            foreach ($judges as $judge) {
                $primarySum += (float) ($scoresIndexed[$contestant->id][$judge->id][$primaryCriterion->id] ?? 0.0);
            }
            $primaryCritAvg = $this->round3($primarySum / $judges->count());

            $chairmanRank = $chairman !== null ? $perJudgeRanks[$chairman->id][$contestant->id] : null;
            $chairmanScore = $chairman !== null ? $judgeTotalsMatrix[$chairman->id][$contestant->id] : null;

            return [
                'contestant_id' => $contestant->id,
                'judge_scores' => $judgeScores,
                'mean_score' => $meanScore,
                'trimmed_mean' => $trimmedMean,
                'rank_sum' => round($rankSum, 2, PHP_ROUND_HALF_UP),
                'first_place_count' => $firstPlaceCount,
                'deduction_points' => round($deductionPoints, 2, PHP_ROUND_HALF_UP),
                'primary_crit_avg' => $primaryCritAvg,
                'chairman_rank' => $chairmanRank,
                'chairman_score' => $chairmanScore,
            ];
        });

        return $this->rankAndFinalize($round, $rows);
    }

    protected function round3(float $value): float
    {
        return round($value, 3, PHP_ROUND_HALF_UP);
    }

    protected function round2(float $value): float
    {
        return round($value, 2, PHP_ROUND_HALF_UP);
    }

    /**
     * @return Collection<int, Contestant>
     */
    protected function resolveActiveContestants(Round $round): Collection
    {
        $roundContestants = $round->contestants()
            ->where('contestants.status', 'active')
            ->get();

        if ($roundContestants->isNotEmpty()) {
            return $roundContestants;
        }

        return $round->event->contestants()
            ->where('status', 'active')
            ->orderBy('candidate_number')
            ->get();
    }

    /**
     * @return Collection<int, Criterion>
     */
    protected function resolveLeafCriteria(Round $round): Collection
    {
        $all = $round->criteria->sortBy('sequence')->values();
        $parentIds = $all->pluck('parent_id')->filter()->unique()->all();

        return $all->reject(fn (Criterion $c): bool => in_array($c->id, $parentIds, true))->values();
    }

    /**
     * @return array<int, array<int, array<int, float>>>
     */
    protected function indexScores(Round $round): array
    {
        $indexed = [];
        foreach ($round->scores as $score) {
            $indexed[$score->contestant_id][$score->judge_id][$score->criterion_id] = (float) $score->raw_score;
        }

        return $indexed;
    }

    /**
     * @param  Collection<int, Criterion>  $leafCriteria
     * @param  array<int, float>  $judgeCriterionScores
     */
    protected function computeJudgeTotal(Collection $leafCriteria, array $judgeCriterionScores): float
    {
        $total = 0.0;
        foreach ($leafCriteria as $criterion) {
            $raw = $judgeCriterionScores[$criterion->id] ?? (float) $criterion->min_score;
            $weighted = $this->round3($raw * ((float) $criterion->weight_percentage / 100.0));
            $total += $weighted;
        }

        return $this->round3($total);
    }

    /**
     * @param  Collection<int, Judge>  $judges
     * @param  Collection<int, Contestant>  $contestants
     * @param  array<int, array<int, float>>  $judgeTotalsMatrix
     * @return array<int, array<int, float>>
     */
    protected function computePerJudgeOrdinalRanks(
        Collection $judges,
        Collection $contestants,
        array $judgeTotalsMatrix
    ): array {
        $ranks = [];

        foreach ($judges as $judge) {
            $items = $contestants->map(fn (Contestant $c): array => [
                'contestant_id' => $c->id,
                'total' => $judgeTotalsMatrix[$judge->id][$c->id],
            ])->sortByDesc('total')->values()->all();

            $count = count($items);
            $i = 0;
            while ($i < $count) {
                $j = $i;
                while ($j + 1 < $count && abs($items[$j + 1]['total'] - $items[$i]['total']) < 0.0005) {
                    $j++;
                }

                // Positions are 1-based: ($i + 1) through ($j + 1)
                $avgRank = (($i + 1) + ($j + 1)) / 2.0;
                for ($k = $i; $k <= $j; $k++) {
                    $ranks[$judge->id][$items[$k]['contestant_id']] = round($avgRank, 2, PHP_ROUND_HALF_UP);
                }

                $i = $j + 1;
            }
        }

        return $ranks;
    }

    /**
     * Shared DTO builder & tie-detection loop for all strategies.
     *
     * @param  array<int, array<string, mixed>>  $sortedRows
     * @param  callable(array<string, mixed>, array<string, mixed>): string|null  $tieBreakerLabelResolver
     * @return Collection<int, RoundResultDTO>
     */
    protected function buildRankedDTOs(
        Round $round,
        array $sortedRows,
        callable $isSamePrimaryMetric,
        callable $isCompletelyIdentical,
        callable $tieBreakerLabelResolver
    ): Collection {
        $totalRows = count($sortedRows);
        $dtos = [];

        $currentRank = 1;
        for ($idx = 0; $idx < $totalRows; $idx++) {
            $row = $sortedRows[$idx];
            $prev = $idx > 0 ? $sortedRows[$idx - 1] : null;
            $next = $idx + 1 < $totalRows ? $sortedRows[$idx + 1] : null;

            $tiedWithPrevExact = $prev !== null && $isCompletelyIdentical($prev, $row);
            $tiedWithNextExact = $next !== null && $isCompletelyIdentical($row, $next);

            if ($idx > 0 && ! $tiedWithPrevExact) {
                $currentRank = $idx + 1;
            }

            $tieBreakRule = 'NONE';
            if ($prev !== null && $isSamePrimaryMetric($prev, $row) && ! $tiedWithPrevExact) {
                $tieBreakRule = $tieBreakerLabelResolver($prev, $row) ?? 'NONE';
            } elseif ($next !== null && $isSamePrimaryMetric($row, $next) && ! $tiedWithNextExact) {
                $tieBreakRule = $tieBreakerLabelResolver($row, $next) ?? 'NONE';
            }

            $dtos[] = new RoundResultDTO(
                roundId: $round->id,
                contestantId: $row['contestant_id'],
                meanScore: $row['mean_score'],
                trimmedMean: $row['trimmed_mean'],
                rankSum: $row['rank_sum'],
                firstPlaceCount: $row['first_place_count'],
                deductionPoints: $row['deduction_points'],
                netComposite: $row['net_composite'],
                cumulativeScore: null,
                primaryCritAvg: $row['primary_crit_avg'],
                chairmanRank: $row['chairman_rank'],
                finalRank: $currentRank,
                isTied: $tiedWithPrevExact || $tiedWithNextExact,
                tieBreakRuleApplied: $tieBreakRule,
                isQualified: $currentRank <= $round->advancement_count,
                judgeBreakdownSnapshot: $row['judge_scores'],
            );
        }

        return collect($dtos);
    }
}
