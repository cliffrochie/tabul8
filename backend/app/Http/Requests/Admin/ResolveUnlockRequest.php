<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class ResolveUnlockRequest extends FormRequest
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
            'round_id' => ['required', 'integer', 'exists:rounds,id'],
            'contestant_id' => ['required', 'integer', 'exists:contestants,id'],
            'judge_id' => ['required', 'integer', 'exists:judges,id'],
            'decision' => ['required', 'in:APPROVE,REJECT'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
