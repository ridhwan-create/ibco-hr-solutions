<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMaklumatJawatanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('positions.manage') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'id_pekerja' => [
                'required',
                'integer',
                Rule::exists('ibco.maklumatpekerja', 'id')->where('rcd_enable', 1),
            ],
            'date_lapordiri' => ['required', 'date'],
            'date_tempohcubaan' => [
                'nullable',
                'date',
                'after_or_equal:date_lapordiri',
            ],
            'id_department' => [
                'required',
                'integer',
                Rule::exists('ibco.xdepartment', 'id')->where('rcd_enable', 1),
            ],
            'jawatan' => ['required', 'string', 'max:100'],
            'salary' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'id_bank' => [
                'nullable',
                'integer',
                Rule::exists('ibco.xbank', 'id')->where('rcd_enable', 1),
            ],
            'noakaun' => ['nullable', 'string', 'max:20'],
            'noepf' => ['nullable', 'string', 'max:20'],
            'nosocso' => ['nullable', 'string', 'max:20'],
            'jumlahcuti' => ['nullable', 'integer', 'min:0', 'max:365'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'id_pekerja' => 'pekerja',
            'date_lapordiri' => 'tarikh berkuat kuasa',
            'date_tempohcubaan' => 'tarikh tamat tempoh percubaan',
            'id_department' => 'jabatan atau unit',
            'jawatan' => 'nama jawatan',
            'salary' => 'gaji asas',
            'id_bank' => 'bank',
            'noakaun' => 'nombor akaun',
            'noepf' => 'nombor KWSP',
            'nosocso' => 'nombor PERKESO',
            'jumlahcuti' => 'kelayakan cuti',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_tempohcubaan.after_or_equal' => 'Tarikh tamat tempoh percubaan tidak boleh lebih awal daripada tarikh berkuat kuasa.',
            'salary.max' => 'Gaji asas melebihi had yang dibenarkan oleh database.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $nullableFields = [
            'date_tempohcubaan',
            'salary',
            'id_bank',
            'noakaun',
            'noepf',
            'nosocso',
            'jumlahcuti',
        ];

        $normalized = collect($nullableFields)
            ->filter(fn (string $field) => $this->has($field))
            ->mapWithKeys(function (string $field) {
                $value = $this->input($field);

                return [$field => $value === '' ? null : $value];
            })
            ->all();

        if ($this->has('jawatan') && is_string($this->input('jawatan'))) {
            $normalized['jawatan'] = trim($this->input('jawatan'));
        }

        foreach (['noakaun', 'noepf', 'nosocso'] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $normalized[$field] = trim($this->input($field)) ?: null;
            }
        }

        $this->merge($normalized);
    }
}
