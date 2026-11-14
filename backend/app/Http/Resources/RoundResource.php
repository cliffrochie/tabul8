<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class RoundResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'name' => $this->name,
            'sequence' => $this->sequence,
            'scoring_strategy' => $this->scoring_strategy,
            'weight_in_overall' => (float) $this->weight_in_overall,
            'is_back_to_zero' => $this->is_back_to_zero,
            'cutoff_mode' => $this->cutoff_mode ?? 'top_n_advance',
            'advancement_count' => $this->advancement_count,
            'eliminate_count' => (int) ($this->eliminate_count ?? 0),
            'status' => $this->status,
            'is_locked' => $this->is_locked,
            'criteria' => CriterionResource::collection($this->whenLoaded('criteria')),
        ];
    }
}
