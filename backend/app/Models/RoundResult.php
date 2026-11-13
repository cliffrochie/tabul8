<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RoundResult extends Model
{
    public const CREATED_AT = null;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'round_id',
        'contestant_id',
        'mean_score',
        'trimmed_mean',
        'rank_sum',
        'first_place_count',
        'deduction_points',
        'net_composite',
        'cumulative_score',
        'primary_crit_avg',
        'chairman_rank',
        'final_rank',
        'is_tied',
        'tie_break_rule_applied',
        'is_qualified',
        'judge_breakdown_snapshot',
        'computed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mean_score' => 'decimal:3',
            'trimmed_mean' => 'decimal:3',
            'rank_sum' => 'decimal:2',
            'first_place_count' => 'integer',
            'deduction_points' => 'decimal:2',
            'net_composite' => 'decimal:3',
            'cumulative_score' => 'decimal:3',
            'primary_crit_avg' => 'decimal:3',
            'chairman_rank' => 'decimal:2',
            'final_rank' => 'integer',
            'is_tied' => 'boolean',
            'is_qualified' => 'boolean',
            'judge_breakdown_snapshot' => 'array',
            'computed_at' => 'datetime',
        ];
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    public function contestant(): BelongsTo
    {
        return $this->belongsTo(Contestant::class);
    }
}
