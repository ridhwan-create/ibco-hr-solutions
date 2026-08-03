<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('claims.approve') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'approved_amount' => [
                'nullable',
                'numeric',
                'min:0.01',
                'required_if:status,approved',
            ],
            'scheduled_payroll_period' => [
                'nullable',
                'date_format:Y-m',
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
