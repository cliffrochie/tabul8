<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class EventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'event_type' => $this->event_type,
            'status' => $this->status,
            'active_round_id' => $this->active_round_id,
            'on_stage_contestant_id' => $this->on_stage_contestant_id,
            'led_mode' => $this->led_mode,
            'revealed_contestant_ids' => $this->revealed_contestant_ids ?? [],
            'settings' => $this->settings ?? [],
        ];
    }
}
