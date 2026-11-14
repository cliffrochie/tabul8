<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class ImportContestantsCsvRequest extends FormRequest
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
            'file' => ['required_without:csv_text', 'file', 'mimes:csv,txt', 'max:2048'],
            'csv_text' => ['required_without:file', 'string'],
        ];
    }
}
