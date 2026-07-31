<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOvertimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('overtime.apply') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'overtime_type_id' => ['required', 'integer', 'min:1'],
            'work_date' => ['required', 'date', 'after_or_equal:-30 days', 'before_or_equal:+30 days'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'break_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'work_description' => ['required', 'string', 'min:5', 'max:2000'],
            'attachment' => [
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:5120',
            ],
        ];
    }
}
