<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Event extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'subtitle',
        'event_type',
        'status',
        'active_round_id',
        'on_stage_contestant_id',
        'led_mode',
        'revealed_contestant_ids',
        'settings',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'revealed_contestant_ids' => 'array',
            'settings' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(Round::class)->orderBy('sequence');
    }

    public function contestants(): HasMany
    {
        return $this->hasMany(Contestant::class)->orderBy('candidate_number');
    }

    public function judges(): HasMany
    {
        return $this->hasMany(Judge::class)->orderBy('judge_number');
    }

    public function activeRound(): BelongsTo
    {
        return $this->belongsTo(Round::class, 'active_round_id');
    }

    public function onStageContestant(): BelongsTo
    {
        return $this->belongsTo(Contestant::class, 'on_stage_contestant_id');
    }
}
