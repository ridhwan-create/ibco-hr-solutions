<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveEmployeeUserLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('attendance.settings') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('user_roles', 'user_id')
                    ->where('role', UserRole::Employee->value),
            ],
            'employee_id' => ['required', 'integer', 'min:1'],
            'office_location_id' => [
                'required',
                'integer',
                Rule::exists('office_locations', 'id')->where('is_active', true),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'Sila pilih akaun Employee.',
            'user_id.exists' => 'Akaun yang dipilih mesti mempunyai role Employee.',
            'employee_id.required' => 'Sila pilih rekod pekerja.',
            'office_location_id.required' => 'Sila pilih lokasi pejabat.',
            'office_location_id.exists' => 'Lokasi pejabat mesti aktif.',
        ];
    }
}
