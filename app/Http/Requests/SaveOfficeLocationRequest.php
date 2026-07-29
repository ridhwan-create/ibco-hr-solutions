<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveOfficeLocationRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['required', 'integer', 'min:20', 'max:5000'],
            'accuracy_limit_meters' => ['required', 'integer', 'min:10', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama lokasi pejabat wajib diisi.',
            'latitude.required' => 'Latitude pejabat wajib diisi.',
            'longitude.required' => 'Longitude pejabat wajib diisi.',
            'radius_meters.min' => 'Radius minimum ialah 20 meter.',
            'radius_meters.max' => 'Radius maksimum ialah 5,000 meter.',
            'accuracy_limit_meters.min' => 'Had ketepatan GPS minimum ialah 10 meter.',
            'accuracy_limit_meters.max' => 'Had ketepatan GPS maksimum ialah 1,000 meter.',
        ];
    }
}
