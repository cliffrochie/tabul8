<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

final class Judge extends Authenticatable
{
    use HasApiTokens;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'judge_number',
        'name',
        'role_title',
        'access_pin_hash',
        'plain_pin_reference',
        'is_chairman',
        'is_active',
        'device_fingerprint',
        'last_seen_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'access_pin_hash',
        'plain_pin_reference',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'judge_number' => 'integer',
            'is_chairman' => 'boolean',
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(Score::class);
    }
}
