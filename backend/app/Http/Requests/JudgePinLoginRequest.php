<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class JudgePinLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'pin' => ['required', 'string', 'size:6'],
            'device_fingerprint' => ['nullable', 'string', 'max:255'],
        ];
    }
}
