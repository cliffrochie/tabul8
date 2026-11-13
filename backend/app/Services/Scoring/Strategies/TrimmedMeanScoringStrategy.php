<?php

namespace App\Services\Scoring\Strategies;

use App\Exceptions\InsufficientJudgesForTrimmedMeanException;
use App\Models\Round;
use Illuminate\Support\Collection;

final class TrimmedMeanScoringStrategy extends AbstractScoringStrategy
{
    protected function validateJudgePanel(Collection $judges): void
    {
        if ($judges->count() < 5) {
            throw new InsufficientJudgesForTrimmedMeanException($judges->count());
        }
    }

    protected function rankAndFinalize(Round $round, Collection $rows): Collection
    {
        $deductBeforeRank = (bool) ($round->settings['deduct_before_judge_rank'] ?? false);

        $prepared = $rows->map(function (array $row) use ($deductBeforeRank): array {
            $row['net_composite'] = $deductBeforeRank
                ? $row['trimmed_mean']
                : max(0.0, $this->round3($row['trimmed_mean'] - $row['deduction_points']));

            $row['untrimmed_net'] = $deductBeforeRank
                ? $row['mean_score']
                : max(0.0, $this->round3($row['mean_score'] - $row['deduction_points']));

            $row['judge_scores'] = $this->markSingleDroppedMinMax($row['judge_scores']);

            return $row;
        })->all();

        usort($prepared, function (array $a, array $b): int {
            $dispCmp = $this->round2($b['net_composite']) <=> $this->round2($a['net_composite']);
            if ($dispCmp !== 0) {
                return $dispCmp;
            }

            $untrimmedCmp = $b['untrimmed_net'] <=> $a['untrimmed_net'];
            if ($untrimmedCmp !== 0) {
                return $untrimmedCmp;
            }

            $critCmp = $b['primary_crit_avg'] <=> $a['primary_crit_avg'];
            if ($critCmp !== 0) {
                return $critCmp;
            }

            return ($b['chairman_score'] ?? 0.0) <=> ($a['chairman_score'] ?? 0.0);
        });

        return $this->buildRankedDTOs(
            $round,
            $prepared,
            fn (array $a, array $b): bool => $this->round2($a['net_composite']) === $this->round2($b['net_composite']),
            fn (array $a, array $b): bool => $this->round2($a['net_composite']) === $this->round2($b['net_composite'])
                && $a['untrimmed_net'] === $b['untrimmed_net']
                && $a['primary_crit_avg'] === $b['primary_crit_avg']
                && ($a['chairman_score'] ?? 0.0) === ($b['chairman_score'] ?? 0.0),
            function (array $a, array $b): ?string {
                if ($a['untrimmed_net'] !== $b['untrimmed_net']) {
                    return 'UNTRIMMED_MEAN';
                }
                if ($a['primary_crit_avg'] !== $b['primary_crit_avg']) {
                    return 'PRIMARY_CRITERION';
                }
                if (($a['chairman_score'] ?? 0.0) !== ($b['chairman_score'] ?? 0.0)) {
                    return 'CHAIRMAN_SCORE';
                }

                return null;
            }
        );
    }

    /**
     * Drops exactly one instance of the min score and one instance of the max score.
     *
     * @param  array<int, array{judge_id: int, judge_number: int, score: float, rank: float, is_dropped: bool}>  $judgeScores
     * @return array<int, array{judge_id: int, judge_number: int, score: float, rank: float, is_dropped: bool}>
     */
    private function markSingleDroppedMinMax(array $judgeScores): array
    {
        $minIdx = null;
        $maxIdx = null;

        foreach ($judgeScores as $idx => $entry) {
            if ($minIdx === null || $entry['score'] < $judgeScores[$minIdx]['score']) {
                $minIdx = $idx;
            }
            if ($maxIdx === null || $entry['score'] > $judgeScores[$maxIdx]['score']) {
                $maxIdx = $idx;
            }
        }

        if ($minIdx === $maxIdx && count($judgeScores) > 1) {
            $maxIdx = count($judgeScores) - 1;
        }

        foreach ($judgeScores as $idx => &$entry) {
            $entry['is_dropped'] = ($idx === $minIdx || $idx === $maxIdx);
        }

        return $judgeScores;
    }
}
