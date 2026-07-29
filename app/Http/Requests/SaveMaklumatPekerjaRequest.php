<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMaklumatPekerjaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('employees.manage') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $employeeId = $this->route('id');

        return [
            'employeeID' => [
                'required',
                'string',
                'max:15',
                Rule::unique('ibco.maklumatpekerja', 'employeeID')->ignore($employeeId),
            ],
            'nric' => [
                'required',
                'digits:12',
                Rule::unique('ibco.maklumatpekerja', 'nric')->ignore($employeeId),
            ],
            'nama' => ['required', 'string', 'max:255'],
            'alamat' => ['nullable', 'string', 'max:255'],
            'jantina' => [
                'nullable',
                Rule::exists('ibco.xjantina', 'id')->where('rcd_enable', 1),
            ],
            'tarikhlahir' => ['nullable', 'date', 'before_or_equal:today'],
            'agama' => [
                'nullable',
                Rule::exists('ibco.xagama', 'id')->where('rcd_enable', 1),
            ],
            'bangsa' => [
                'nullable',
                Rule::exists('ibco.xbangsa', 'id')->where('rcd_enable', 1),
            ],
            'kewarganegaraan' => ['nullable', 'string', 'max:255'],
            'statusperkahwinan' => [
                'nullable',
                Rule::exists('ibco.xstatusperkahwinan', 'id')->where('rcd_enable', 1),
            ],
            'notel' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'status' => [
                'required',
                Rule::exists('ibco.xstatus', 'id')->where('rcd_enable', 1),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'employeeID' => 'ID pekerja',
            'nric' => 'nombor kad pengenalan',
            'nama' => 'nama pekerja',
            'alamat' => 'alamat',
            'jantina' => 'jantina',
            'tarikhlahir' => 'tarikh lahir',
            'agama' => 'agama',
            'bangsa' => 'bangsa',
            'kewarganegaraan' => 'kewarganegaraan',
            'statusperkahwinan' => 'status perkahwinan',
            'notel' => 'nombor telefon',
            'email' => 'e-mel',
            'status' => 'status pekerja',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employeeID.unique' => 'ID pekerja ini telah digunakan.',
            'nric.digits' => 'Nombor kad pengenalan mesti mengandungi 12 digit.',
            'nric.unique' => 'Nombor kad pengenalan ini telah digunakan.',
            'tarikhlahir.before_or_equal' => 'Tarikh lahir tidak boleh melebihi tarikh hari ini.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $stringFields = [
            'employeeID',
            'nama',
            'alamat',
            'kewarganegaraan',
            'notel',
            'email',
        ];

        $normalized = collect($stringFields)
            ->filter(fn (string $field) => $this->has($field))
            ->mapWithKeys(function (string $field) {
                $value = $this->input($field);

                return [$field => is_string($value) ? trim($value) : $value];
            })
            ->all();

        if ($this->has('nric') && is_string($this->input('nric'))) {
            $normalized['nric'] = preg_replace('/[\s-]+/', '', $this->input('nric'));
        }

        $this->merge($normalized);
    }
}
