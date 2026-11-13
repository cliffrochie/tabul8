<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class StageStateUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<int, int>  $revealedContestantIds
     */
    public function __construct(
        public readonly int $eventId,
        public readonly ?int $activeRoundId,
        public readonly string $scoringStrategy,
        public readonly ?int $onStageContestantId,
        public readonly bool $isRoundFrozen,
        public readonly string $ledMode,
        public readonly array $revealedContestantIds = [],
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel("event.{$this->eventId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'StageStateUpdated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'active_round_id' => $this->activeRoundId,
            'scoring_strategy' => $this->scoringStrategy,
            'on_stage_contestant_id' => $this->onStageContestantId,
            'is_round_frozen' => $this->isRoundFrozen,
            'led_mode' => $this->ledMode,
            'revealed_contestant_ids' => $this->revealedContestantIds,
        ];
    }
}
