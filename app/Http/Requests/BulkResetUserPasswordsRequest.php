<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkResetUserPasswordsRequest extends FormRequest
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
            'user_ids' => ['required', 'array', 'min:1', 'max:200'],
            'user_ids.*' => [
                'required',
                'integer',
                'distinct',
                'exists:users,id',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_ids.required' => 'Pilih sekurang-kurangnya seorang pengguna.',
            'user_ids.min' => 'Pilih sekurang-kurangnya seorang pengguna.',
            'user_ids.max' => 'Maksimum 200 pengguna boleh diproses pada satu masa.',
            'user_ids.*.distinct' => 'Senarai pengguna mengandungi pilihan berganda.',
            'user_ids.*.exists' => 'Sebahagian pengguna yang dipilih tidak lagi wujud.',
        ];
    }
}
