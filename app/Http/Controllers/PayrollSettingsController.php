<?php

namespace App\Http\Controllers;

use App\Models\EmployeePayrollComponent;
use App\Models\EmployeeSalaryProfile;
use App\Models\PayrollComponent;
use App\Models\PayrollSetting;
use App\Support\AuditLogger;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PayrollSettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $activeJobIds = DB::connection('ibco')
            ->table('maklumatjawatan')
            ->where('rcd_enable', 1)
            ->selectRaw('id_pekerja, MAX(id) as job_id')
            ->groupBy('id_pekerja');
        $employees = DB::connection('ibco')
            ->table('maklumatpekerja as employee')
            ->leftJoinSub($activeJobIds, 'active_job', function ($join) {
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
                'job.jawatan as position',
                'job.salary as legacy_salary',
            ])
            ->orderBy('employee.nama')
            ->paginate(20)
            ->withQueryString();
        $employeeIds = collect($employees->items())->pluck('id')->all();
        $profiles = EmployeeSalaryProfile::query()
            ->whereIn('employee_id', $employeeIds)
            ->get()
            ->keyBy('employee_id');
        $assignments = EmployeePayrollComponent::query()
            ->with('component:id,code,name,type,is_active')
            ->whereIn('employee_id', $employeeIds)
            ->orderBy('id')
            ->get()
            ->groupBy('employee_id');

        $employees->through(function ($employee) use ($profiles, $assignments) {
            $profile = $profiles[$employee->id] ?? null;

            return [
                'id' => (int) $employee->id,
                'employee_number' => $employee->employee_number,
                'employee_name' => $employee->employee_name,
                'position' => $employee->position,
                'legacy_salary' => $employee->legacy_salary !== null
                    ? (float) $employee->legacy_salary
                    : null,
                'salary_profile' => $profile
                    ? [
                        'id' => $profile->getKey(),
                        'basic_salary' => (float) $profile->basic_salary,
                        'effective_from' => $profile->effective_from?->toDateString(),
                        'is_active' => $profile->is_active,
                        'notes' => $profile->notes,
                    ]
                    : null,
                'components' => $assignments
                    ->get($employee->id, collect())
                    ->map(fn (EmployeePayrollComponent $assignment) => [
                        'id' => $assignment->getKey(),
                        'payroll_component_id' => $assignment->payroll_component_id,
                        'code' => $assignment->component?->code,
                        'name' => $assignment->component?->name,
                        'type' => $assignment->component?->type,
                        'amount' => (float) $assignment->amount,
                        'effective_from' => $assignment->effective_from?->toDateString(),
                        'effective_to' => $assignment->effective_to?->toDateString(),
                        'is_active' => $assignment->is_active,
                    ])
                    ->values(),
            ];
        });
        $settings = PayrollSetting::query()->firstOrCreate(
            ['id' => 1],
            [
                'currency' => 'MYR',
                'working_days_divisor' => 26,
                'daily_hours' => 8,
                'include_approved_overtime' => true,
                'deduct_unpaid_leave' => true,
            ],
        );

        return Inertia::render('PayrollSettings/Index', [
            'settings' => [
                'currency' => $settings->currency,
                'working_days_divisor' => (float) $settings->working_days_divisor,
                'daily_hours' => (float) $settings->daily_hours,
                'include_approved_overtime' => $settings->include_approved_overtime,
                'deduct_unpaid_leave' => $settings->deduct_unpaid_leave,
            ],
            'components' => PayrollComponent::query()
                ->orderBy('type')
                ->orderBy('name')
                ->get()
                ->map(fn (PayrollComponent $component) => [
                    'id' => $component->getKey(),
                    'code' => $component->code,
                        'name' => $component->name,
                        'type' => $component->type,
                        'is_active' => $component->is_active,
                        'is_epf_wage' => $component->is_epf_wage,
                        'is_socso_wage' => $component->is_socso_wage,
                        'is_eis_wage' => $component->is_eis_wage,
                        'is_pcb_wage' => $component->is_pcb_wage,
                ]),
            'employees' => $employees,
            'filters' => ['search' => $search],
            'statistics' => [
                'active_employees' => DB::connection('ibco')
                    ->table('maklumatpekerja')
                    ->where('rcd_enable', 1)
                    ->count(),
                'configured_profiles' => EmployeeSalaryProfile::query()
                    ->where('is_active', true)
                    ->count(),
                'active_components' => PayrollComponent::query()
                    ->where('is_active', true)
                    ->count(),
            ],
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'working_days_divisor' => ['required', 'numeric', 'min:1', 'max:31'],
            'daily_hours' => ['required', 'numeric', 'min:1', 'max:24'],
            'include_approved_overtime' => ['required', 'boolean'],
            'deduct_unpaid_leave' => ['required', 'boolean'],
        ]);
        $settings = PayrollSetting::query()->firstOrCreate(['id' => 1]);
        $oldValues = $settings->only(array_keys($validated));
        $settings->update([
            ...$validated,
            'currency' => strtoupper($validated['currency']),
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);

        AuditLogger::record(
            $request,
            'payroll_settings.updated',
            'payroll_settings',
            $settings->getKey(),
            oldValues: $oldValues,
            newValues: $settings->fresh()->only(array_keys($validated)),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Tetapan pengiraan payroll telah dikemas kini.',
        ]);
    }

    public function storeComponent(Request $request): RedirectResponse
    {
        $validated = $this->validatedComponent($request);
        $component = PayrollComponent::query()->create([
            ...$validated,
            'created_by' => $request->user()->getAuthIdentifier(),
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);

        AuditLogger::record(
            $request,
            'payroll_component.created',
            'payroll_components',
            $component->getKey(),
            newValues: $component->only([
                'code',
                'name',
                'type',
                'is_active',
                'is_epf_wage',
                'is_socso_wage',
                'is_eis_wage',
                'is_pcb_wage',
            ]),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Komponen payroll telah ditambah.',
        ]);
    }

    public function updateComponent(
        Request $request,
        PayrollComponent $payrollComponent,
    ): RedirectResponse {
        $validated = $this->validatedComponent($request, $payrollComponent);
        $oldValues = $payrollComponent->only(array_keys($validated));
        $payrollComponent->update([
            ...$validated,
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);

        AuditLogger::record(
            $request,
            'payroll_component.updated',
            'payroll_components',
            $payrollComponent->getKey(),
            oldValues: $oldValues,
            newValues: $payrollComponent->fresh()->only(array_keys($validated)),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Komponen payroll telah dikemas kini.',
        ]);
    }

    public function toggleComponent(
        Request $request,
        PayrollComponent $payrollComponent,
    ): RedirectResponse {
        $oldStatus = $payrollComponent->is_active;
        $payrollComponent->update([
            'is_active' => ! $oldStatus,
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);

        AuditLogger::record(
            $request,
            $payrollComponent->is_active
                ? 'payroll_component.activated'
                : 'payroll_component.deactivated',
            'payroll_components',
            $payrollComponent->getKey(),
            oldValues: ['is_active' => $oldStatus],
            newValues: ['is_active' => $payrollComponent->is_active],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => $payrollComponent->is_active
                ? 'Komponen payroll telah diaktifkan.'
                : 'Komponen payroll telah dinyahaktifkan.',
        ]);
    }

    public function saveSalaryProfile(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'min:1'],
            'basic_salary' => ['required', 'numeric', 'min:0.01', 'max:9999999.99'],
            'effective_from' => ['required', 'date'],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->ensureEmployeeExists((int) $validated['employee_id']);
        $validated['effective_from'] = Carbon::parse(
            $validated['effective_from'],
        )->startOfMonth()->toDateString();
        $profile = EmployeeSalaryProfile::query()
            ->where('employee_id', $validated['employee_id'])
            ->first();
        $oldValues = $profile?->only([
            'employee_id',
            'basic_salary',
            'effective_from',
            'is_active',
            'notes',
        ]) ?? [];
        $profile = EmployeeSalaryProfile::query()->updateOrCreate(
            ['employee_id' => $validated['employee_id']],
            [
                ...$validated,
                'updated_by' => $request->user()->getAuthIdentifier(),
            ],
        );

        AuditLogger::record(
            $request,
            $profile->wasRecentlyCreated
                ? 'salary_profile.created'
                : 'salary_profile.updated',
            'employee_salary_profiles',
            $profile->getKey(),
            oldValues: $oldValues,
            newValues: $profile->only([
                'employee_id',
                'basic_salary',
                'effective_from',
                'is_active',
                'notes',
            ]),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Profil gaji pekerja telah disimpan.',
        ]);
    }

    public function saveEmployeeComponent(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'min:1'],
            'payroll_component_id' => [
                'required',
                'integer',
                'exists:payroll_components,id',
            ],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999.99'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->ensureEmployeeExists((int) $validated['employee_id']);

        if (
            EmployeeSalaryProfile::query()
                ->where('employee_id', $validated['employee_id'])
                ->doesntExist()
        ) {
            throw ValidationException::withMessages([
                'employee_id' => 'Simpan profil gaji pekerja terlebih dahulu.',
            ]);
        }

        $component = PayrollComponent::query()
            ->whereKey($validated['payroll_component_id'])
            ->where('is_active', true)
            ->first();

        if (! $component) {
            throw ValidationException::withMessages([
                'payroll_component_id' => 'Komponen payroll tidak aktif atau tidak sah.',
            ]);
        }

        $validated['effective_from'] = Carbon::parse(
            $validated['effective_from'],
        )->startOfMonth()->toDateString();
        $validated['effective_to'] = isset($validated['effective_to'])
            ? Carbon::parse($validated['effective_to'])->endOfMonth()->toDateString()
            : null;
        $assignment = EmployeePayrollComponent::query()
            ->where('employee_id', $validated['employee_id'])
            ->where('payroll_component_id', $validated['payroll_component_id'])
            ->first();
        $oldValues = $assignment?->only([
            'employee_id',
            'payroll_component_id',
            'amount',
            'effective_from',
            'effective_to',
            'is_active',
        ]) ?? [];
        $assignment = EmployeePayrollComponent::query()->updateOrCreate(
            [
                'employee_id' => $validated['employee_id'],
                'payroll_component_id' => $validated['payroll_component_id'],
            ],
            [
                ...$validated,
                'updated_by' => $request->user()->getAuthIdentifier(),
            ],
        );

        AuditLogger::record(
            $request,
            $assignment->wasRecentlyCreated
                ? 'employee_payroll_component.created'
                : 'employee_payroll_component.updated',
            'employee_payroll_components',
            $assignment->getKey(),
            oldValues: $oldValues,
            newValues: $assignment->only([
                'employee_id',
                'payroll_component_id',
                'amount',
                'effective_from',
                'effective_to',
                'is_active',
            ]),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => "{$component->name} untuk pekerja telah disimpan.",
        ]);
    }

    public function toggleEmployeeComponent(
        Request $request,
        EmployeePayrollComponent $employeePayrollComponent,
    ): RedirectResponse {
        $oldStatus = $employeePayrollComponent->is_active;

        if (
            ! $oldStatus
            && PayrollComponent::query()
                ->whereKey($employeePayrollComponent->payroll_component_id)
                ->where('is_active', true)
                ->doesntExist()
        ) {
            throw ValidationException::withMessages([
                'component' => 'Aktifkan komponen payroll utama terlebih dahulu.',
            ]);
        }

        $employeePayrollComponent->update([
            'is_active' => ! $oldStatus,
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);

        AuditLogger::record(
            $request,
            $employeePayrollComponent->is_active
                ? 'employee_payroll_component.activated'
                : 'employee_payroll_component.deactivated',
            'employee_payroll_components',
            $employeePayrollComponent->getKey(),
            oldValues: ['is_active' => $oldStatus],
            newValues: [
                'employee_id' => $employeePayrollComponent->employee_id,
                'is_active' => $employeePayrollComponent->is_active,
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => $employeePayrollComponent->is_active
                ? 'Komponen pekerja telah diaktifkan.'
                : 'Komponen pekerja telah dinyahaktifkan.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedComponent(
        Request $request,
        ?PayrollComponent $component = null,
    ): array {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:32',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('payroll_components', 'code')->ignore($component),
            ],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(['earning', 'deduction'])],
            'is_active' => ['required', 'boolean'],
            'is_epf_wage' => ['required', 'boolean'],
            'is_socso_wage' => ['required', 'boolean'],
            'is_eis_wage' => ['required', 'boolean'],
            'is_pcb_wage' => ['required', 'boolean'],
        ]);
        $validated['code'] = strtoupper($validated['code']);

        return $validated;
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
