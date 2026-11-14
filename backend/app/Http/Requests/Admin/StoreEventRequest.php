<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class StoreEventRequest extends FormRequest
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
            'title' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'max:180'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'event_type' => [$this->isMethod('post') ? 'required' : 'sometimes', 'in:pageant,singing,dance,custom'],
            'status' => ['sometimes', 'in:setup,live,completed'],
            'settings' => ['sometimes', 'array'],
            'preset' => ['sometimes', 'nullable', 'in:pageant,singing,dance,custom'],
            'clone_from_event_id' => ['sometimes', 'nullable', 'integer', 'exists:events,id'],
        ];
    }
}
