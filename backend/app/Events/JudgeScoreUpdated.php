<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class JudgeScoreUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<int, array<string, mixed>>  $leaderboard
     */
    public function __construct(
        public readonly int $eventId,
        public readonly int $roundId,
        public readonly int $judgeId,
        public readonly int $contestantId,
        public readonly float $weightedTotal,
        public readonly string $status,
        public readonly string $updatedAt,
        public readonly array $leaderboard = [],
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tabulator.{$this->eventId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'JudgeScoreUpdated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'round_id' => $this->roundId,
            'judge_id' => $this->judgeId,
            'contestant_id' => $this->contestantId,
            'weighted_total' => $this->weightedTotal,
            'status' => $this->status,
            'updated_at' => $this->updatedAt,
            'leaderboard' => $this->leaderboard,
        ];
    }
}
