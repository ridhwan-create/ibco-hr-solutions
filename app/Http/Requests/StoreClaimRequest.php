<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('claims.apply') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'claim_type_id' => ['required', 'integer', 'min:1'],
            'expense_date' => [
                'required',
                'date',
                'after_or_equal:-12 months',
                'before_or_equal:today',
            ],
            'merchant_name' => ['nullable', 'string', 'max:150'],
            'receipt_number' => ['nullable', 'string', 'max:100'],
            'requested_amount' => ['required', 'numeric', 'min:0.01', 'max:9999999.99'],
            'description' => ['required', 'string', 'min:5', 'max:2000'],
            'receipts' => ['nullable', 'array', 'max:5'],
            'receipts.*' => [
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:5120',
            ],
        ];
    }
}
