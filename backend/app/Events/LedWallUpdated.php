<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class LedWallUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>|null  $onStageContestant
     * @param  array<int, int>  $revealedContestantIds
     * @param  array<int, array<string, mixed>>  $finalistsPublicCards
     */
    public function __construct(
        public readonly int $eventId,
        public readonly string $ledMode,
        public readonly ?array $onStageContestant,
        public readonly array $revealedContestantIds = [],
        public readonly array $finalistsPublicCards = [],
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("led.{$this->eventId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'LedWallUpdated';
    }

    /**
     * Zero raw score exposure on public LED channel.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'led_mode' => $this->ledMode,
            'on_stage_contestant' => $this->onStageContestant,
            'revealed_contestant_ids' => $this->revealedContestantIds,
            'finalists_public_cards' => $this->finalistsPublicCards,
        ];
    }
}
