<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class RoundResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'round_id' => $this->round_id,
            'contestant_id' => $this->contestant_id,
            'mean_score' => (float) $this->mean_score,
            'trimmed_mean' => (float) $this->trimmed_mean,
            'rank_sum' => (float) $this->rank_sum,
            'first_place_count' => $this->first_place_count,
            'deduction_points' => (float) $this->deduction_points,
            'net_composite' => (float) $this->net_composite,
            'cumulative_score' => $this->cumulative_score !== null ? (float) $this->cumulative_score : null,
            'primary_crit_avg' => (float) $this->primary_crit_avg,
            'chairman_rank' => $this->chairman_rank !== null ? (float) $this->chairman_rank : null,
            'final_rank' => $this->final_rank,
            'is_tied' => $this->is_tied,
            'tie_break_rule_applied' => $this->tie_break_rule_applied,
            'is_qualified' => $this->is_qualified,
            'judge_breakdown_snapshot' => $this->judge_breakdown_snapshot,
            'computed_at' => $this->computed_at?->toIso8601String(),
        ];
    }
}
