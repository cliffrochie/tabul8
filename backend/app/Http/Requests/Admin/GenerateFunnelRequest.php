<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class GenerateFunnelRequest extends FormRequest
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
            'round_count' => ['required', 'integer', 'min:1', 'max:10'],
            'final_winners_count' => ['required', 'integer', 'min:1', 'max:50'],
            'final_back_to_zero' => ['sometimes', 'boolean'],
        ];
    }
}
