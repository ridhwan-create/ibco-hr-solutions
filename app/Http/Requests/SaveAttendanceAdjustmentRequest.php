<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveAttendanceAdjustmentRequest extends FormRequest
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
            'clock_in_at' => ['required', 'date'],
            'clock_out_at' => ['nullable', 'date', 'after_or_equal:clock_in_at'],
            'cancelled' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'clock_in_at.required' => 'Waktu masuk wajib diisi.',
            'clock_out_at.after_or_equal' => 'Waktu keluar tidak boleh lebih awal daripada waktu masuk.',
            'reason.required' => 'Alasan pembetulan wajib diisi.',
            'reason.min' => 'Alasan pembetulan mestilah sekurang-kurangnya 5 aksara.',
        ];
    }
}
