<?php

namespace App\Http\Requests\Admin;

use App\Models\Contestant;
use App\Models\Round;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class StoreDeductionRequest extends FormRequest
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
            'points_deducted' => ['required', 'numeric', 'min:0', 'max:100'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $round = Round::find($this->integer('round_id'));
            if ($round === null) {
                return;
            }

            $contestantMatches = Contestant::where('id', $this->integer('contestant_id'))
                ->where('event_id', $round->event_id)
                ->exists();

            if (! $contestantMatches) {
                $validator->errors()->add('contestant_id', 'Contestant does not belong to the specified round event.');
            }
        });
    }
}
