<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CriterionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'round_id' => $this->round_id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'description' => $this->description,
            'weight_percentage' => (float) $this->weight_percentage,
            'min_score' => (float) $this->min_score,
            'max_score' => (float) $this->max_score,
            'score_step' => (float) $this->score_step,
            'sequence' => $this->sequence,
        ];
    }
}
