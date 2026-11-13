<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Contestant extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'candidate_number',
        'name',
        'origin_or_group',
        'performance_meta',
        'category',
        'photo_path',
        'asset_ratio',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'candidate_number' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function rounds(): BelongsToMany
    {
        return $this->belongsToMany(Round::class, 'round_contestants')
            ->withPivot(['performance_order', 'qualified_from_rank'])
            ->withTimestamps();
    }

    public function scores(): HasMany
    {
        return $this->hasMany(Score::class);
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(Deduction::class);
    }
}
