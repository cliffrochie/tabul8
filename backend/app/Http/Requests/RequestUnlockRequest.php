<?php

namespace App\Http\Requests;

use App\Models\Judge;
use Illuminate\Foundation\Http\FormRequest;

final class RequestUnlockRequest extends FormRequest
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
            'contestant_id' => ['required', 'integer', 'exists:contestants,id'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
