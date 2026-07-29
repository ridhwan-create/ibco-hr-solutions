<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('users.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'distinct', Rule::enum(UserRole::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'roles.required' => 'Sila pilih sekurang-kurangnya satu role pengguna.',
            'roles.array' => 'Senarai role pengguna tidak sah.',
            'roles.min' => 'Sila pilih sekurang-kurangnya satu role pengguna.',
            'roles.*.enum' => 'Role yang dipilih tidak sah.',
            'roles.*.distinct' => 'Role yang sama tidak boleh dipilih dua kali.',
        ];
    }
}
