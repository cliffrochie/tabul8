<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class StoreRoundRequest extends FormRequest
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
            'name' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'max:150'],
            'scoring_strategy' => ['sometimes', 'in:weighted,ordinal,trimmed'],
            'weight_in_overall' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'is_back_to_zero' => ['sometimes', 'boolean'],
            'cutoff_mode' => ['sometimes', 'in:top_n_advance,eliminate_n'],
            'advancement_count' => ['sometimes', 'integer', 'min:1'],
            'eliminate_count' => ['sometimes', 'integer', 'min:0'],
            'settings' => ['sometimes', 'array'],
        ];
    }
}
