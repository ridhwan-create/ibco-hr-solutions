<?php

namespace App\Support;

use App\Models\EmployeeLeaveRequest;
use App\Models\ClaimRequest;
use App\Models\EmployeePayrollComponent;
use App\Models\EmployeeSalaryProfile;
use App\Models\OvertimeRequest;
use App\Models\PayrollEntry;
use App\Models\PayrollEntryItem;
use App\Models\PayrollRun;
use App\Models\PayrollSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollCalculator
{
    public function __construct(
        private readonly StatutoryCalculator $statutoryCalculator,
        private readonly WorkScheduleResolver $scheduleResolver,
    ) {}

    public function recalculate(PayrollRun $run, ?User $actor = null): PayrollRun
    {
        if ($run->status !== 'draft') {
            throw ValidationException::withMessages([
                'payroll' => 'Hanya payroll berstatus Draf boleh dikira semula.',
            ]);
        }

        return DB::transaction(function () use ($run, $actor) {
            $lockedRun = PayrollRun::query()
                ->lockForUpdate()
                ->findOrFail($run->getKey());

            if ($lockedRun->status !== 'draft') {
                throw ValidationException::withMessages([
                    'payroll' => 'Status payroll telah berubah dan tidak boleh dikira semula.',
                ]);
            }

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
            $periodStart = $lockedRun->period_start->copy()->startOfMonth();
            $periodEnd = $periodStart->copy()->endOfMonth();
            $profiles = EmployeeSalaryProfile::query()
                ->with(['recurringComponents.component'])
                ->where('is_active', true)
                ->whereDate('effective_from', '<=', $periodStart)
                ->orderBy('employee_id')
                ->get();
            $employees = $this->employeeMap($profiles->pluck('employee_id'));
            $eligibleProfiles = $profiles
                ->filter(fn (EmployeeSalaryProfile $profile) => isset(
                    $employees[(string) $profile->employee_id],
                ))
                ->values();

            if ($eligibleProfiles->isEmpty()) {
                throw ValidationException::withMessages([
                    'payroll' => 'Tiada profil gaji aktif yang layak untuk tempoh ini.',
                ]);
            }

            $eligibleEmployeeIds = $eligibleProfiles
                ->pluck('employee_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            ClaimRequest::query()
                ->where('payroll_run_id', $lockedRun->getKey())
                ->whereNull('paid_at')
                ->update(['payroll_run_id' => null]);
            PayrollEntry::query()
                ->where('payroll_run_id', $lockedRun->getKey())
                ->whereNotIn('employee_id', $eligibleEmployeeIds)
                ->delete();

            $overtimeByEmployee = $settings->include_approved_overtime
                ? OvertimeRequest::query()
                    ->with('overtimeType:id,name,rate_multiplier')
                    ->whereIn('employee_id', $eligibleEmployeeIds)
                    ->where('status', 'approved')
                    ->whereBetween('work_date', [$periodStart, $periodEnd])
                    ->get()
                    ->groupBy('employee_id')
                : collect();
            $unpaidLeaveByEmployee = $settings->deduct_unpaid_leave
                ? EmployeeLeaveRequest::query()
                    ->with('systemLeaveType:id,code,name')
                    ->whereIn('employee_id', $eligibleEmployeeIds)
                    ->where('status', 'approved')
                    ->whereDate('start_date', '<=', $periodEnd)
                    ->whereDate('end_date', '>=', $periodStart)
                    ->whereHas(
                        'systemLeaveType',
                        fn ($query) => $query->where('code', 'UNPAID'),
                    )
                    ->get()
                    ->groupBy('employee_id')
                : collect();
            $claimsByEmployee = ClaimRequest::query()
                ->with('claimType:id,name')
                ->whereIn('employee_id', $eligibleEmployeeIds)
                ->where('status', 'approved')
                ->whereNull('paid_at')
                ->whereDate('scheduled_payroll_period', $periodStart)
                ->get()
                ->groupBy('employee_id');
            foreach ($eligibleProfiles as $profile) {
                $employee = $employees[(string) $profile->employee_id];
                $entry = PayrollEntry::query()->updateOrCreate(
                    [
                        'payroll_run_id' => $lockedRun->getKey(),
                        'employee_id' => $profile->employee_id,
                    ],
                    [
                        'employee_number' => $employee['employee_number'],
                        'employee_name' => $employee['employee_name'],
                        'basic_salary' => $profile->basic_salary,
                        'calculated_at' => now(),
                    ],
                );
                $entry->items()->where('is_manual', false)->delete();

                $this->createItem($entry, [
                    'code' => 'BASIC_SALARY',
                    'name' => 'Gaji Asas',
                    'type' => 'earning',
                    'category' => 'basic',
                    'quantity' => 1,
                    'rate' => $profile->basic_salary,
                    'multiplier' => 1,
                    'amount' => $profile->basic_salary,
                    'source_type' => EmployeeSalaryProfile::class,
                    'source_id' => $profile->getKey(),
                    'is_epf_wage' => true,
                    'is_socso_wage' => true,
                    'is_eis_wage' => true,
                    'is_pcb_wage' => true,
                ]);

                foreach ($profile->recurringComponents as $assignment) {
                    $component = $assignment->component;

                    if (
                        ! $assignment->is_active
                        || ! $component
                        || ! $component->is_active
                        || $assignment->effective_from->isAfter($periodStart)
                        || (
                            $assignment->effective_to
                            && $assignment->effective_to->isBefore($periodStart)
                        )
                    ) {
                        continue;
                    }

                    $this->createItem($entry, [
                        'payroll_component_id' => $component->getKey(),
                        'code' => $component->code,
                        'name' => $component->name,
                        'type' => $component->type,
                        'category' => 'recurring',
                        'quantity' => 1,
                        'rate' => $assignment->amount,
                        'multiplier' => 1,
                        'amount' => $assignment->amount,
                        'source_type' => EmployeePayrollComponent::class,
                        'source_id' => $assignment->getKey(),
                        'is_epf_wage' => $component->is_epf_wage,
                        'is_socso_wage' => $component->is_socso_wage,
                        'is_eis_wage' => $component->is_eis_wage,
                        'is_pcb_wage' => $component->is_pcb_wage,
                    ]);
                }

                $minuteRate = round(
                    (float) $profile->basic_salary
                    / (float) $settings->working_days_divisor
                    / (float) $settings->daily_hours
                    / 60,
                    4,
                );

                foreach ($overtimeByEmployee->get($profile->employee_id, collect()) as $overtime) {
                    $minutes = (int) ($overtime->approved_minutes ?? 0);
                    $multiplier = (float) ($overtime->overtimeType?->rate_multiplier ?? 1);
                    $amount = round($minuteRate * $minutes * $multiplier, 2);

                    if ($minutes < 1 || $amount <= 0) {
                        continue;
                    }

                    $this->createItem($entry, [
                        'code' => 'OT-'.str_pad((string) $overtime->getKey(), 5, '0', STR_PAD_LEFT),
                        'name' => 'Kerja Lebih Masa — '.(
                            $overtime->overtimeType?->name ?? 'OT Diluluskan'
                        ),
                        'type' => 'earning',
                        'category' => 'overtime',
                        'quantity' => $minutes,
                        'rate' => $minuteRate,
                        'multiplier' => $multiplier,
                        'amount' => $amount,
                        'source_type' => OvertimeRequest::class,
                        'source_id' => $overtime->getKey(),
                        'notes' => $overtime->work_date?->format('d/m/Y'),
                        'is_epf_wage' => false,
                        'is_socso_wage' => true,
                        'is_eis_wage' => true,
                        'is_pcb_wage' => true,
                    ]);
                }

                $dailyRate = round(
                    (float) $profile->basic_salary
                    / (float) $settings->working_days_divisor,
                    4,
                );

                foreach ($unpaidLeaveByEmployee->get($profile->employee_id, collect()) as $leave) {
                    $days = $this->unpaidDaysInPeriod(
                        $leave,
                        (int) $profile->employee_id,
                        $periodStart,
                        $periodEnd,
                    );
                    $amount = round($dailyRate * $days, 2);

                    if ($days <= 0 || $amount <= 0) {
                        continue;
                    }

                    $this->createItem($entry, [
                        'code' => 'UNPAID-'.str_pad((string) $leave->getKey(), 5, '0', STR_PAD_LEFT),
                        'name' => 'Potongan Cuti Tanpa Gaji',
                        'type' => 'deduction',
                        'category' => 'unpaid_leave',
                        'quantity' => $days,
                        'rate' => $dailyRate,
                        'multiplier' => 1,
                        'amount' => $amount,
                        'source_type' => EmployeeLeaveRequest::class,
                        'source_id' => $leave->getKey(),
                        'notes' => sprintf(
                            '%s hingga %s',
                            $leave->start_date->format('d/m/Y'),
                            $leave->end_date->format('d/m/Y'),
                        ),
                        'is_epf_wage' => true,
                        'is_socso_wage' => true,
                        'is_eis_wage' => true,
                        'is_pcb_wage' => true,
                    ]);
                }

                foreach ($claimsByEmployee->get($profile->employee_id, collect()) as $claim) {
                    $amount = round((float) ($claim->approved_amount ?? 0), 2);

                    if ($amount <= 0) {
                        continue;
                    }

                    $this->createItem($entry, [
                        'code' => 'CLAIM-'.str_pad(
                            (string) $claim->getKey(),
                            5,
                            '0',
                            STR_PAD_LEFT,
                        ),
                        'name' => 'Bayaran Balik — '.(
                            $claim->claimType?->name ?? 'Tuntutan Diluluskan'
                        ),
                        'type' => 'earning',
                        'category' => 'claim_reimbursement',
                        'quantity' => 1,
                        'rate' => $amount,
                        'multiplier' => 1,
                        'amount' => $amount,
                        'source_type' => ClaimRequest::class,
                        'source_id' => $claim->getKey(),
                        'notes' => $claim->expense_date?->format('d/m/Y')
                            .($claim->receipt_number
                                ? " · Resit {$claim->receipt_number}"
                                : ''),
                        'is_epf_wage' => false,
                        'is_socso_wage' => false,
                        'is_eis_wage' => false,
                        'is_pcb_wage' => false,
                    ]);
                    $claim->update(['payroll_run_id' => $lockedRun->getKey()]);
                }

                $this->refreshEntryTotals($entry);
                $this->statutoryCalculator->apply(
                    $entry,
                    $periodStart,
                    $actor,
                );
                $this->refreshEntryTotals($entry);
            }

            $lockedRun->update([
                'currency' => $settings->currency,
                'generated_at' => now(),
                'generated_by' => $actor?->getAuthIdentifier()
                    ?? $lockedRun->generated_by,
            ]);
            $this->refreshRunTotals($lockedRun);

            return $lockedRun->fresh();
        });
    }

    public function refreshEntryTotals(PayrollEntry $entry): PayrollEntry
    {
        $items = $entry->items()->get();
        $sum = fn (callable $filter): float => round(
            (float) $items->filter($filter)->sum('amount'),
            2,
        );
        $basicSalary = $sum(
            fn (PayrollEntryItem $item) => $item->category === 'basic',
        );
        $overtimeAmount = $sum(
            fn (PayrollEntryItem $item) => $item->category === 'overtime',
        );
        $claimReimbursements = $sum(
            fn (PayrollEntryItem $item) => $item->category === 'claim_reimbursement',
        );
        $overtimeMinutes = (int) $items
            ->where('category', 'overtime')
            ->sum(fn (PayrollEntryItem $item) => (float) $item->quantity);
        $unpaidLeaveAmount = $sum(
            fn (PayrollEntryItem $item) => $item->category === 'unpaid_leave',
        );
        $unpaidLeaveDays = round(
            (float) $items
                ->where('category', 'unpaid_leave')
                ->sum(fn (PayrollEntryItem $item) => (float) $item->quantity),
            1,
        );
        $recurringEarnings = $sum(
            fn (PayrollEntryItem $item) => $item->category === 'recurring'
                && $item->type === 'earning',
        );
        $recurringDeductions = $sum(
            fn (PayrollEntryItem $item) => $item->category === 'recurring'
                && $item->type === 'deduction',
        );
        $manualEarnings = $sum(
            fn (PayrollEntryItem $item) => $item->is_manual
                && $item->type === 'earning',
        );
        $manualDeductions = $sum(
            fn (PayrollEntryItem $item) => $item->is_manual
                && $item->type === 'deduction',
        );
        $grossPay = $sum(
            fn (PayrollEntryItem $item) => $item->type === 'earning',
        );
        $totalDeductions = $sum(
            fn (PayrollEntryItem $item) => $item->type === 'deduction',
        );

        $entry->update([
            'basic_salary' => $basicSalary,
            'overtime_minutes' => $overtimeMinutes,
            'overtime_amount' => $overtimeAmount,
            'claim_reimbursements' => $claimReimbursements,
            'unpaid_leave_days' => $unpaidLeaveDays,
            'unpaid_leave_amount' => $unpaidLeaveAmount,
            'recurring_earnings' => $recurringEarnings,
            'recurring_deductions' => $recurringDeductions,
            'manual_earnings' => $manualEarnings,
            'manual_deductions' => $manualDeductions,
            'gross_pay' => $grossPay,
            'total_deductions' => $totalDeductions,
            'net_pay' => round($grossPay - $totalDeductions, 2),
            'calculated_at' => now(),
        ]);

        return $entry->fresh();
    }

    public function refreshRunTotals(PayrollRun $run): PayrollRun
    {
        $totals = PayrollEntry::query()
            ->where('payroll_run_id', $run->getKey())
            ->selectRaw(
                'COUNT(*) as employee_count, '
                .'COALESCE(SUM(basic_salary), 0) as total_basic_salary, '
                .'COALESCE(SUM(gross_pay), 0) as total_earnings, '
                .'COALESCE(SUM(total_deductions), 0) as total_deductions, '
                .'COALESCE(SUM(net_pay), 0) as total_net_pay',
            )
            ->first();

        $run->update([
            'employee_count' => (int) $totals->employee_count,
            'total_basic_salary' => round((float) $totals->total_basic_salary, 2),
            'total_earnings' => round((float) $totals->total_earnings, 2),
            'total_deductions' => round((float) $totals->total_deductions, 2),
            'total_net_pay' => round((float) $totals->total_net_pay, 2),
            'total_employee_statutory' => round((float) $run
                ->entries()
                ->join(
                    'payroll_statutory_snapshots as statutory',
                    'statutory.payroll_entry_id',
                    '=',
                    'payroll_entries.id',
                )
                ->sum('statutory.total_employee_deductions'), 2),
            'total_employer_statutory' => round((float) $run
                ->entries()
                ->join(
                    'payroll_statutory_snapshots as statutory',
                    'statutory.payroll_entry_id',
                    '=',
                    'payroll_entries.id',
                )
                ->sum('statutory.total_employer_contributions'), 2),
            'total_pcb' => round((float) $run
                ->entries()
                ->join(
                    'payroll_statutory_snapshots as statutory',
                    'statutory.payroll_entry_id',
                    '=',
                    'payroll_entries.id',
                )
                ->sum('statutory.pcb'), 2),
        ]);

        return $run->fresh();
    }

    /**
     * @param  array<string, float|string|null>|null  $override
     */
    public function refreshStatutory(
        PayrollEntry $entry,
        ?User $actor = null,
        ?array $override = null,
    ): PayrollEntry {
        $run = $entry->payrollRun()->firstOrFail();
        $this->statutoryCalculator->apply(
            $entry,
            $run->period_start,
            $actor,
            $override,
        );
        $entry = $this->refreshEntryTotals($entry);
        $this->refreshRunTotals($run);

        return $entry->fresh(['statutorySnapshot', 'items']);
    }

    /**
     * @param  Collection<int, int|string>  $employeeIds
     * @return array<string, array{employee_number: string|null, employee_name: string}>
     */
    private function employeeMap(Collection $employeeIds): array
    {
        if ($employeeIds->isEmpty()) {
            return [];
        }

        return DB::connection('ibco')
            ->table('maklumatpekerja')
            ->whereIn('id', $employeeIds->all())
            ->where('rcd_enable', 1)
            ->get(['id', 'employeeID', 'nama'])
            ->mapWithKeys(fn ($employee) => [
                (string) $employee->id => [
                    'employee_number' => $employee->employeeID,
                    'employee_name' => $employee->nama
                        ?? "Pekerja #{$employee->id}",
                ],
            ])
            ->all();
    }

    private function unpaidDaysInPeriod(
        EmployeeLeaveRequest $leave,
        int $employeeId,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd,
    ): float {
        $start = $leave->start_date->copy()->max($periodStart);
        $end = $leave->end_date->copy()->min($periodEnd);

        if ($start->isAfter($end)) {
            return 0;
        }

        if ($leave->duration_type !== 'full_day') {
            return $this->scheduleResolver->isScheduledWorkDay(
                $employeeId,
                $start,
            ) ? 0.5 : 0;
        }

        $days = 0;

        for (
            $date = CarbonImmutable::parse($start);
            $date->lessThanOrEqualTo($end);
            $date = $date->addDay()
        ) {
            if ($this->scheduleResolver->isScheduledWorkDay(
                $employeeId,
                $date,
            )) {
                $days++;
            }
        }

        return (float) $days;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createItem(PayrollEntry $entry, array $attributes): PayrollEntryItem
    {
        return $entry->items()->create([
            'is_manual' => false,
            'is_epf_wage' => false,
            'is_socso_wage' => false,
            'is_eis_wage' => false,
            'is_pcb_wage' => false,
            ...$attributes,
        ]);
    }
}
