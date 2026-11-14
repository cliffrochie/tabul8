<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && $user->is_active
            && $user->role === 'super_admin';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User|null $routeUser */
        $routeUser = $this->route('user');

        return [
            'name' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'max:120'],
            'email' => [
                $this->isMethod('post') ? 'required' : 'sometimes',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore($routeUser?->id),
            ],
            'password' => [$this->isMethod('post') ? 'required' : 'sometimes', 'nullable', 'string', 'min:8', 'max:100'],
            'role' => [$this->isMethod('post') ? 'required' : 'sometimes', 'in:super_admin,tabulator,auditor,mc_stage'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
