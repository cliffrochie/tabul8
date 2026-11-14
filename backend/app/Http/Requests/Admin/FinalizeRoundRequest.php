<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class FinalizeRoundRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && $user->is_active
            && in_array($user->role, ['super_admin', 'tabulator'], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'activate_next_round' => ['sometimes', 'boolean'],
            'push_to_led_reveal' => ['sometimes', 'boolean'],
            'qualified_contestant_ids' => ['sometimes', 'array', 'min:1'],
            'qualified_contestant_ids.*' => ['integer', 'exists:contestants,id'],
            'override_reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
