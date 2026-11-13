<?php

namespace App\Http\Requests;

use App\Models\Judge;
use Illuminate\Foundation\Http\FormRequest;

final class LockScoresRequest extends FormRequest
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
            'contestant_ids' => ['required_without:lock_all', 'array', 'min:1'],
            'contestant_ids.*' => ['integer', 'exists:contestants,id'],
            'lock_all' => ['sometimes', 'boolean'],
        ];
    }
}
