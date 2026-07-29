<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordGeoAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('attendance.clock') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'gt:0', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'latitude.required' => 'Lokasi semasa tidak diterima. Sila hidupkan GPS dan cuba lagi.',
            'longitude.required' => 'Lokasi semasa tidak diterima. Sila hidupkan GPS dan cuba lagi.',
            'accuracy.required' => 'Ketepatan GPS tidak diterima. Sila cuba semula.',
        ];
    }
}
