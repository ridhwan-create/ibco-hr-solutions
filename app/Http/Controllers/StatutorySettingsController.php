<?php

namespace App\Http\Controllers;

use App\Models\EmployeeStatutoryProfile;
use App\Models\PayrollSetting;
use App\Models\StatutorySetting;
use App\Support\AuditLogger;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StatutorySettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $activeJobs = DB::connection('ibco')
            ->table('maklumatjawatan')
            ->where('rcd_enable', 1)
            ->selectRaw('id_pekerja, MAX(id) as job_id')
            ->groupBy('id_pekerja');
        $employees = DB::connection('ibco')
            ->table('maklumatpekerja as employee')
            ->leftJoinSub($activeJobs, 'active_job', function ($join) {
                $join->on('active_job.id_pekerja', '=', 'employee.id');
            })
            ->leftJoin('maklumatjawatan as job', 'job.id', '=', 'active_job.job_id')
            ->where('employee.rcd_enable', 1)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('employee.nama', 'like', "%{$search}%")
                        ->orWhere('employee.employeeID', 'like', "%{$search}%")
                        ->orWhere('job.jawatan', 'like', "%{$search}%");
                });
            })
            ->select([
                'employee.id',
                'employee.employeeID as employee_number',
                'employee.nama as employee_name',
                'employee.tarikhlahir as birth_date',
                'employee.kewarganegaraan as nationality',
                'job.jawatan as position',
                'job.noepf as legacy_epf_number',
                'job.nosocso as legacy_socso_number',
            ])
            ->orderBy('employee.nama')
            ->paginate(20)
            ->withQueryString();
        $profiles = EmployeeStatutoryProfile::query()
            ->whereIn(
                'employee_id',
                collect($employees->items())->pluck('id')->all(),
            )
            ->get()
            ->keyBy('employee_id');
        $employees->through(function ($employee) use ($profiles) {
            $profile = $profiles[$employee->id] ?? null;
            $age = $employee->birth_date
                ? (int) Carbon::parse($employee->birth_date)->diffInYears(now())
                : null;

            return [
                'id' => (int) $employee->id,
                'employee_number' => $employee->employee_number,
                'employee_name' => $employee->employee_name,
                'position' => $employee->position,
                'birth_date' => $employee->birth_date,
                'age' => $age,
                'nationality' => $employee->nationality,
                'legacy_epf_number' => $employee->legacy_epf_number,
                'legacy_socso_number' => $employee->legacy_socso_number,
                'statutory_profile' => $profile
                    ? [
                        'id' => $profile->getKey(),
                        'kwsp_category' => $profile->kwsp_category,
                        'socso_category' => $profile->socso_category,
                        'eis_enabled' => $profile->eis_enabled,
                        'pcb_method' => $profile->pcb_method,
                        'pcb_monthly_amount' => (float) $profile->pcb_monthly_amount,
                        'epf_number' => $profile->epf_number,
                        'socso_number' => $profile->socso_number,
                        'tax_number' => $profile->tax_number,
                        'effective_from' => $profile->effective_from?->toDateString(),
                        'is_active' => $profile->is_active,
                        'notes' => $profile->notes,
                    ]
                    : null,
            ];
        });
        $statutory = StatutorySetting::query()->firstOrCreate(['id' => 1]);
        $payroll = PayrollSetting::query()->firstOrCreate(['id' => 1]);

        return Inertia::render('StatutorySettings/Index', [
            'statutorySettings' => $this->settingsPayload($statutory),
            'payslipSettings' => [
                'company_name' => $payroll->company_name,
                'company_registration_no' => $payroll->company_registration_no,
                'company_address' => $payroll->company_address,
                'payslip_note' => $payroll->payslip_note,
            ],
            'employees' => $employees,
            'filters' => ['search' => $search],
            'statistics' => [
                'active_employees' => DB::connection('ibco')
                    ->table('maklumatpekerja')
                    ->where('rcd_enable', 1)
                    ->count(),
                'configured_profiles' => EmployeeStatutoryProfile::query()
                    ->where('is_active', true)
                    ->count(),
                'pcb_profiles' => EmployeeStatutoryProfile::query()
                    ->where('is_active', true)
                    ->where('pcb_monthly_amount', '>', 0)
                    ->count(),
            ],
            'kwspCategories' => [
                ['value' => 'citizen_below_60', 'label' => 'Warganegara / PR bawah 60'],
                ['value' => 'citizen_60_plus', 'label' => 'Warganegara 60 tahun ke atas'],
                ['value' => 'pr_below_60', 'label' => 'PR bawah 60 tahun'],
                ['value' => 'pr_60_plus', 'label' => 'PR 60 tahun ke atas'],
                ['value' => 'non_malaysian', 'label' => 'Bukan warganegara (2% + 2%)'],
                ['value' => 'exempt', 'label' => 'Dikecualikan'],
            ],
            'socsoCategories' => [
                ['value' => 'first', 'label' => 'Kategori Pertama'],
                ['value' => 'second', 'label' => 'Kategori Kedua'],
                ['value' => 'exempt', 'label' => 'Dikecualikan'],
            ],
        ]);
    }

    public function updateRates(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'kwsp_effective_from' => ['required', 'date'],
            'kwsp_table_limit' => ['required', 'numeric', 'min:1', 'max:99999999'],
            'kwsp_employer_threshold' => ['required', 'numeric', 'min:1', 'max:99999999'],
            'kwsp_employee_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'kwsp_employer_rate_low' => ['required', 'numeric', 'min:0', 'max:100'],
            'kwsp_employer_rate_high' => ['required', 'numeric', 'min:0', 'max:100'],
            'kwsp_age60_employee_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'kwsp_age60_employer_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'kwsp_pr_age60_employee_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'kwsp_pr_age60_employer_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'kwsp_foreign_employee_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'kwsp_foreign_employer_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'socso_effective_from' => ['required', 'date'],
            'socso_wage_ceiling' => ['required', 'numeric', 'min:1', 'max:99999999'],
            'socso_first_employer_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'socso_first_employee_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'socso_skbbk_employee_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'socso_second_employer_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'eis_effective_from' => ['required', 'date'],
            'eis_wage_ceiling' => ['required', 'numeric', 'min:1', 'max:99999999'],
            'eis_employee_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'eis_employer_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'pcb_tax_year' => ['required', 'integer', 'min:2020', 'max:2100'],
        ]);
        $settings = StatutorySetting::query()->firstOrCreate(['id' => 1]);
        $oldValues = $settings->only(array_keys($validated));
        $settings->update([
            ...$validated,
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);

        AuditLogger::record(
            $request,
            'statutory_settings.updated',
            'statutory_settings',
            $settings->getKey(),
            oldValues: $oldValues,
            newValues: $settings->fresh()->only(array_keys($validated)),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Kadar statutori telah dikemas kini. Kira semula payroll Draf untuk menggunakannya.',
        ]);
    }

    public function updatePayslip(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:150'],
            'company_registration_no' => ['nullable', 'string', 'max:80'],
            'company_address' => ['nullable', 'string', 'max:500'],
            'payslip_note' => ['nullable', 'string', 'max:255'],
        ]);
        $settings = PayrollSetting::query()->firstOrCreate(['id' => 1]);
        $oldValues = $settings->only(array_keys($validated));
        $settings->update([
            ...$validated,
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);

        AuditLogger::record(
            $request,
            'payslip_settings.updated',
            'payroll_settings',
            $settings->getKey(),
            oldValues: $oldValues,
            newValues: $settings->fresh()->only(array_keys($validated)),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Maklumat kepala slip gaji telah dikemas kini.',
        ]);
    }

    public function saveProfile(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'min:1'],
            'kwsp_category' => [
                'required',
                Rule::in(EmployeeStatutoryProfile::KWSP_CATEGORIES),
            ],
            'socso_category' => [
                'required',
                Rule::in(EmployeeStatutoryProfile::SOCSO_CATEGORIES),
            ],
            'eis_enabled' => ['required', 'boolean'],
            'pcb_method' => ['required', Rule::in(['fixed', 'none'])],
            'pcb_monthly_amount' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'epf_number' => ['nullable', 'string', 'max:80'],
            'socso_number' => ['nullable', 'string', 'max:80'],
            'tax_number' => ['nullable', 'string', 'max:80'],
            'effective_from' => ['required', 'date'],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->ensureEmployeeExists((int) $validated['employee_id']);
        $validated['effective_from'] = Carbon::parse(
            $validated['effective_from'],
        )->startOfMonth()->toDateString();
        $profile = EmployeeStatutoryProfile::query()
            ->where('employee_id', $validated['employee_id'])
            ->first();
        $oldValues = $profile?->only(array_keys($validated)) ?? [];
        $profile = EmployeeStatutoryProfile::query()->updateOrCreate(
            ['employee_id' => $validated['employee_id']],
            [
                ...$validated,
                'updated_by' => $request->user()->getAuthIdentifier(),
            ],
        );

        AuditLogger::record(
            $request,
            $profile->wasRecentlyCreated
                ? 'statutory_profile.created'
                : 'statutory_profile.updated',
            'employee_statutory_profiles',
            $profile->getKey(),
            oldValues: $oldValues,
            newValues: $profile->only(array_keys($validated)),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Profil statutori pekerja telah disimpan.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsPayload(StatutorySetting $settings): array
    {
        $payload = $settings->toArray();

        foreach ([
            'kwsp_table_limit',
            'kwsp_employer_threshold',
            'kwsp_employee_rate',
            'kwsp_employer_rate_low',
            'kwsp_employer_rate_high',
            'kwsp_age60_employee_rate',
            'kwsp_age60_employer_rate',
            'kwsp_pr_age60_employee_rate',
            'kwsp_pr_age60_employer_rate',
            'kwsp_foreign_employee_rate',
            'kwsp_foreign_employer_rate',
            'socso_wage_ceiling',
            'socso_first_employer_rate',
            'socso_first_employee_rate',
            'socso_skbbk_employee_rate',
            'socso_second_employer_rate',
            'eis_wage_ceiling',
            'eis_employee_rate',
            'eis_employer_rate',
        ] as $field) {
            $payload[$field] = (float) $settings->{$field};
        }
        $payload['kwsp_effective_from'] = $settings
            ->kwsp_effective_from?->toDateString();
        $payload['socso_effective_from'] = $settings
            ->socso_effective_from?->toDateString();
        $payload['eis_effective_from'] = $settings
            ->eis_effective_from?->toDateString();

        return $payload;
    }

    private function ensureEmployeeExists(int $employeeId): void
    {
        $exists = DB::connection('ibco')
            ->table('maklumatpekerja')
            ->where('id', $employeeId)
            ->where('rcd_enable', 1)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'employee_id' => 'Rekod pekerja asal tidak aktif atau tidak dijumpai.',
            ]);
        }
    }
}
