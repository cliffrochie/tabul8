<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class StoreCriterionRequest extends FormRequest
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
            'parent_id' => ['nullable', 'integer', 'exists:criteria,id'],
            'name' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'weight_percentage' => [$this->isMethod('post') ? 'required' : 'sometimes', 'numeric', 'min:0', 'max:100'],
            'min_score' => ['sometimes', 'numeric', 'min:0', 'max:999'],
            'max_score' => ['sometimes', 'numeric', 'gt:0', 'max:1000'],
            'score_step' => ['sometimes', 'numeric', 'gt:0', 'max:100'],
            'sequence' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
