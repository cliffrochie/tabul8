<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ContestantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'candidate_number' => $this->candidate_number,
            'name' => $this->name,
            'origin_or_group' => $this->origin_or_group,
            'performance_meta' => $this->performance_meta,
            'category' => $this->category,
            'asset_ratio' => $this->asset_ratio,
            'status' => $this->status,
            'photo_url' => $this->photo_path
                ? ($request->is('api/v1/display/*')
                    ? "/api/v1/display/events/{$this->event_id}/contestants/{$this->id}/photo"
                    : "/api/v1/events/{$this->event_id}/contestants/{$this->id}/photo")
                : null,
            'is_qualified_in_active_round' => (bool) ($this->resource->getAttribute('is_qualified_in_active_round') ?? true),
            'qualified_from_rank' => $this->resource->getAttribute('qualified_from_rank'),
            'qualification_method' => $this->resource->getAttribute('qualification_method'),
            'eliminated_in_round_name' => $this->resource->getAttribute('eliminated_in_round_name'),
        ];
    }
}
