<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManualAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('attendance.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'min:1'],
            'office_location_id' => [
                'required',
                'integer',
                Rule::exists('office_locations', 'id')->where('is_active', true),
            ],
            'attendance_date' => ['required', 'date'],
            'clock_in_at' => ['required', 'date'],
            'clock_out_at' => ['nullable', 'date', 'after_or_equal:clock_in_at'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_id.required' => 'Sila pilih pekerja.',
            'office_location_id.required' => 'Sila pilih lokasi pejabat.',
            'clock_in_at.required' => 'Waktu masuk wajib diisi.',
            'clock_out_at.after_or_equal' => 'Waktu keluar tidak boleh lebih awal daripada waktu masuk.',
            'reason.required' => 'Alasan rekod manual wajib diisi.',
            'reason.min' => 'Alasan mestilah sekurang-kurangnya 5 aksara.',
        ];
    }
}
