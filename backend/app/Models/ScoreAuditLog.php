<?php

namespace App\Models;

use App\Exceptions\ImmutableAuditLogException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ScoreAuditLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'round_id',
        'score_id',
        'judge_id',
        'contestant_id',
        'actor_user_id',
        'action',
        'old_value',
        'new_value',
        'metadata',
        'reason',
        'ip_address',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_value' => 'decimal:3',
            'new_value' => 'decimal:3',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new ImmutableAuditLogException;
        }

        if ($this->created_at === null) {
            $this->created_at = now();
        }

        return parent::save($options);
    }

    public function delete(): ?bool
    {
        throw new ImmutableAuditLogException;
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }
}
