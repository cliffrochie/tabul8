<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class StageSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && $user->is_active
            && in_array($user->role, ['super_admin', 'tabulator', 'mc_stage'], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'active_round_id' => ['sometimes', 'integer', 'exists:rounds,id'],
            'scoring_strategy' => ['sometimes', 'in:weighted,ordinal,trimmed'],
            'on_stage_contestant_id' => ['sometimes', 'nullable', 'integer', 'exists:contestants,id'],
            'is_round_frozen' => ['sometimes', 'boolean'],
            'led_mode' => ['sometimes', 'in:on_stage,criteria,reveal'],
            'revealed_contestant_ids' => ['sometimes', 'array'],
            'revealed_contestant_ids.*' => ['integer', 'exists:contestants,id'],
        ];
    }
}
