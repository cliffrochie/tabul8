<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class StoreContestantRequest extends FormRequest
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
            'candidate_number' => [$this->isMethod('post') ? 'sometimes' : 'sometimes', 'integer', 'min:1'],
            'name' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'max:150'],
            'origin_or_group' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'max:150'],
            'performance_meta' => ['nullable', 'string', 'max:255'],
            'category' => ['sometimes', 'string', 'max:80'],
            'asset_ratio' => ['sometimes', 'in:PORTRAIT_4:5,PHOTO_300x300,WIDE_16:9'],
            'status' => ['sometimes', 'in:active,eliminated,withdrawn'],
        ];
    }
}
