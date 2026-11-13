<?php

namespace App\Services\Scoring\Strategies;

use App\Models\Round;
use Illuminate\Support\Collection;

final class WeightedScoringStrategy extends AbstractScoringStrategy
{
    protected function rankAndFinalize(Round $round, Collection $rows): Collection
    {
        $deductBeforeRank = (bool) ($round->settings['deduct_before_judge_rank'] ?? false);

        $prepared = $rows->map(function (array $row) use ($deductBeforeRank): array {
            $row['net_composite'] = $deductBeforeRank
                ? $row['mean_score']
                : max(0.0, $this->round3($row['mean_score'] - $row['deduction_points']));

            return $row;
        })->all();

        usort($prepared, function (array $a, array $b): int {
            $dispCmp = $this->round2($b['net_composite']) <=> $this->round2($a['net_composite']);
            if ($dispCmp !== 0) {
                return $dispCmp;
            }

            $unroundedCmp = $b['net_composite'] <=> $a['net_composite'];
            if ($unroundedCmp !== 0) {
                return $unroundedCmp;
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
            fn (array $a, array $b): bool => $a['net_composite'] === $b['net_composite']
                && $a['primary_crit_avg'] === $b['primary_crit_avg']
                && ($a['chairman_score'] ?? 0.0) === ($b['chairman_score'] ?? 0.0),
            function (array $a, array $b): ?string {
                if ($a['net_composite'] !== $b['net_composite']) {
                    return 'UNROUNDED_PRECISION';
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
}
