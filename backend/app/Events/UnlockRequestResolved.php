<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class UnlockRequestResolved implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $judgeId,
        public readonly int $roundId,
        public readonly int $contestantId,
        public readonly string $decision,
        public readonly string $newStatus,
        public readonly string $resolvedBy,
        public readonly string $timestamp,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("judge.{$this->judgeId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'UnlockRequestResolved';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'round_id' => $this->roundId,
            'contestant_id' => $this->contestantId,
            'decision' => $this->decision,
            'new_status' => $this->newStatus,
            'resolved_by' => $this->resolvedBy,
            'timestamp' => $this->timestamp,
        ];
    }
}
