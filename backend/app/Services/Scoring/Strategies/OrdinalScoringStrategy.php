<?php

namespace App\Services\Scoring\Strategies;

use App\Models\Round;
use Illuminate\Support\Collection;

final class OrdinalScoringStrategy extends AbstractScoringStrategy
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
            $rankCmp = $a['rank_sum'] <=> $b['rank_sum'];
            if ($rankCmp !== 0) {
                return $rankCmp;
            }

            $firstPlaceCmp = $b['first_place_count'] <=> $a['first_place_count'];
            if ($firstPlaceCmp !== 0) {
                return $firstPlaceCmp;
            }

            $netCmp = $b['net_composite'] <=> $a['net_composite'];
            if ($netCmp !== 0) {
                return $netCmp;
            }

            return ($a['chairman_rank'] ?? INF) <=> ($b['chairman_rank'] ?? INF);
        });

        return $this->buildRankedDTOs(
            $round,
            $prepared,
            fn (array $a, array $b): bool => $a['rank_sum'] === $b['rank_sum'],
            fn (array $a, array $b): bool => $a['rank_sum'] === $b['rank_sum']
                && $a['first_place_count'] === $b['first_place_count']
                && $a['net_composite'] === $b['net_composite']
                && ($a['chairman_rank'] ?? INF) === ($b['chairman_rank'] ?? INF),
            function (array $a, array $b): ?string {
                if ($a['first_place_count'] !== $b['first_place_count']) {
                    return 'FIRST_PLACE_COUNT';
                }
                if ($a['net_composite'] !== $b['net_composite']) {
                    return 'NET_COMPOSITE';
                }
                if (($a['chairman_rank'] ?? INF) !== ($b['chairman_rank'] ?? INF)) {
                    return 'CHAIRMAN_RANK';
                }

                return null;
            }
        );
    }
}
