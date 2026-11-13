<?php

namespace App\Http\Requests;

use App\Models\Judge;
use Illuminate\Foundation\Http\FormRequest;

final class UpsertScoresRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof Judge && $this->user()->is_active;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'round_id' => ['required', 'integer', 'exists:rounds,id'],
            'mutations' => ['required', 'array', 'min:1', 'max:100'],
            'mutations.*.client_mutation_id' => ['required', 'string', 'max:64'],
            'mutations.*.contestant_id' => ['required', 'integer', 'exists:contestants,id'],
            'mutations.*.action' => ['required', 'in:UPSERT_SCORE,LOCK_SHEET'],
            'mutations.*.criterion_id' => ['required_if:mutations.*.action,UPSERT_SCORE', 'nullable', 'integer', 'exists:criteria,id'],
            'mutations.*.raw_score' => ['required_if:mutations.*.action,UPSERT_SCORE', 'nullable', 'numeric'],
            'mutations.*.client_timestamp' => ['required', 'date'],
        ];
    }
}
