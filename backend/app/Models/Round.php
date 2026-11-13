<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Round extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'name',
        'sequence',
        'scoring_strategy',
        'weight_in_overall',
        'is_back_to_zero',
        'advancement_count',
        'status',
        'is_locked',
        'settings',
        'locked_at',
        'finalized_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'weight_in_overall' => 'decimal:2',
            'is_back_to_zero' => 'boolean',
            'advancement_count' => 'integer',
            'is_locked' => 'boolean',
            'settings' => 'array',
            'locked_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(Criterion::class)->orderBy('sequence');
    }

    public function contestants(): BelongsToMany
    {
        return $this->belongsToMany(Contestant::class, 'round_contestants')
            ->withPivot(['performance_order', 'qualified_from_rank'])
            ->withTimestamps()
            ->orderByPivot('performance_order');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(Score::class);
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(Deduction::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(RoundResult::class)->orderBy('final_rank');
    }
}
