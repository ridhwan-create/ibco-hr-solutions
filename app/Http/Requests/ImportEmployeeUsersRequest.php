<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportEmployeeUsersRequest extends FormRequest
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
            'employee_ids' => ['required', 'array', 'min:1', 'max:200'],
            'employee_ids.*' => [
                'required',
                'integer',
                'min:1',
                'distinct',
            ],
            'office_location_id' => [
                'required',
                'integer',
                Rule::exists('office_locations', 'id')
                    ->where('is_active', true),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_ids.required' => 'Pilih sekurang-kurangnya seorang pekerja.',
            'employee_ids.array' => 'Senarai pekerja yang dipilih tidak sah.',
            'employee_ids.min' => 'Pilih sekurang-kurangnya seorang pekerja.',
            'employee_ids.max' => 'Maksimum 200 pekerja boleh diimport pada satu masa.',
            'employee_ids.*.integer' => 'Rekod pekerja yang dipilih tidak sah.',
            'employee_ids.*.distinct' => 'Pekerja yang sama tidak boleh dipilih dua kali.',
            'office_location_id.required' => 'Pilih lokasi pejabat untuk pekerja yang diimport.',
            'office_location_id.exists' => 'Lokasi pejabat yang dipilih mesti aktif.',
        ];
    }
}
