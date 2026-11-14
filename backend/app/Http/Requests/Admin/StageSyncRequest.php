<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StageSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User || ! $user->is_active) {
            return false;
        }

        if ($user->role === 'mc_stage') {
            return ! $this->hasAny(['active_round_id', 'scoring_strategy', 'is_round_frozen']);
        }

        return in_array($user->role, ['super_admin', 'tabulator'], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $eventId = $this->integer('event_id');

        return [
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'active_round_id' => [
                'sometimes',
                'integer',
                Rule::exists('rounds', 'id')->where('event_id', $eventId),
            ],
            'scoring_strategy' => ['sometimes', 'in:weighted,ordinal,trimmed'],
            'on_stage_contestant_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('contestants', 'id')->where('event_id', $eventId),
            ],
            'is_round_frozen' => ['sometimes', 'boolean'],
            'led_mode' => ['sometimes', 'in:on_stage,criteria,reveal'],
            'revealed_contestant_ids' => ['sometimes', 'array'],
            'revealed_contestant_ids.*' => [
                'integer',
                Rule::exists('contestants', 'id')->where('event_id', $eventId),
            ],
        ];
    }
}
