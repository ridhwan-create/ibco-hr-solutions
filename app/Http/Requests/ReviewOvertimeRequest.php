<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewOvertimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('overtime.manage') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'approved_minutes' => [
                'nullable',
                'integer',
                'min:1',
                'required_if:status,approved',
            ],
            'review_notes' => [
                'nullable',
                'string',
                'max:1000',
                'required_if:status,rejected',
            ],
        ];
    }
}
