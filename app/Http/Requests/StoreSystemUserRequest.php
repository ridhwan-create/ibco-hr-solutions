<?php

namespace App\Http\Requests;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSystemUserRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->hasPermission('users.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isEmployee = in_array(
            UserRole::Employee->value,
            (array) $this->input('roles', []),
            true,
        );

        return [
            ...$this->profileRules(),
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'distinct', Rule::enum(UserRole::class)],
            'employee_id' => [
                Rule::requiredIf($isEmployee),
                'nullable',
                'integer',
                'min:1',
            ],
            'office_location_id' => [
                Rule::requiredIf($isEmployee),
                'nullable',
                'integer',
                Rule::exists('office_locations', 'id')->where('is_active', true),
            ],
            'password' => $this->passwordRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama pengguna wajib diisi.',
            'email.required' => 'Alamat e-mel wajib diisi.',
            'email.email' => 'Sila masukkan alamat e-mel yang sah.',
            'email.unique' => 'Alamat e-mel ini sudah digunakan.',
            'roles.required' => 'Sila pilih sekurang-kurangnya satu role pengguna.',
            'roles.array' => 'Senarai role pengguna tidak sah.',
            'roles.min' => 'Sila pilih sekurang-kurangnya satu role pengguna.',
            'roles.*.enum' => 'Role yang dipilih tidak sah.',
            'roles.*.distinct' => 'Role yang sama tidak boleh dipilih dua kali.',
            'employee_id.required' => 'Sila pilih pekerja untuk akaun Employee.',
            'office_location_id.required' => 'Sila pilih lokasi pejabat untuk akaun Employee.',
            'office_location_id.exists' => 'Lokasi pejabat yang dipilih mesti aktif.',
            'password.required' => 'Kata laluan sementara wajib diisi.',
            'password.confirmed' => 'Pengesahan kata laluan tidak sepadan.',
        ];
    }
}
