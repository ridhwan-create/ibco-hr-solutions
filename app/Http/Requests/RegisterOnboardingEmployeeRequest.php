<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterOnboardingEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('onboarding.approve') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'identity_number' => [
                'required',
                'string',
                'min:6',
                'max:40',
                Rule::unique('employee_records', 'identity_number'),
            ],
            'employee_number' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Za-z0-9][A-Za-z0-9\/_-]*$/',
                Rule::unique('employee_records', 'employee_number'),
            ],
            'official_email' => [
                'required',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email'),
                Rule::unique('employee_records', 'official_email'),
            ],
            'office_location_id' => [
                'required',
                'integer',
                Rule::exists('office_locations', 'id')->where('is_active', true),
            ],
            'manager_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'confirmed' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'identity_number.required' => 'Nombor pengenalan calon wajib disahkan.',
            'identity_number.unique' => 'Nombor pengenalan ini telah didaftarkan sebagai pekerja.',
            'employee_number.required' => 'Nombor pekerja wajib diisi.',
            'employee_number.regex' => 'Nombor pekerja hanya boleh mengandungi huruf, nombor, garis condong, sempang atau garis bawah.',
            'employee_number.unique' => 'Nombor pekerja ini telah digunakan.',
            'official_email.required' => 'E-mel log masuk rasmi wajib diisi.',
            'official_email.email' => 'Sila masukkan alamat e-mel rasmi yang sah.',
            'official_email.unique' => 'Alamat e-mel ini telah digunakan oleh akaun lain.',
            'office_location_id.required' => 'Sila pilih lokasi pejabat.',
            'office_location_id.exists' => 'Lokasi pejabat yang dipilih tidak aktif.',
            'confirmed.accepted' => 'Sila sahkan bahawa maklumat pekerja telah disemak.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $identity = $this->input('identity_number');
        $employeeNumber = $this->input('employee_number');
        $email = $this->input('official_email');

        $this->merge([
            'identity_number' => is_string($identity)
                ? strtoupper(preg_replace('/[\s-]+/', '', trim($identity)))
                : $identity,
            'employee_number' => is_string($employeeNumber)
                ? strtoupper(trim($employeeNumber))
                : $employeeNumber,
            'official_email' => is_string($email) ? strtolower(trim($email)) : $email,
        ]);
    }
}
