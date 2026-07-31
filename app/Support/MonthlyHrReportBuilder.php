<?php

namespace App\Support;

use App\Models\EmployeeLeaveRequest;
use App\Models\EmployeeUserLink;
use App\Models\GeoAttendanceRecord;
use App\Models\OfficeLocation;
use App\Models\OvertimeRequest;
use App\Models\PayrollRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MonthlyHrReportBuilder
{
    public function __construct(
        private readonly WorkScheduleResolver $scheduleResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(
        string $period,
        ?int $departmentId = null,
        ?int $officeLocationId = null,
    ): array {
        $periodStart = CarbonImmutable::createFromFormat(
            'Y-m-d',
            "{$period}-01",
        )->startOfMonth();
        $context = $this->employeeContext(
            $departmentId,
            $officeLocationId,
        );
        $periodData = $this->periodData(
            $periodStart,
            $context['employee_ids'],
        );
        $trend = collect(range(5, 0))
            ->map(function (int $monthsAgo) use ($periodStart, $context) {
                $start = $periodStart->subMonths($monthsAgo);
                $data = $this->periodData(
                    $start,
                    $context['employee_ids'],
                );

                return [
                    'period' => $start->format('Y-m'),
                    'label' => $start->translatedFormat('M Y'),
                    'attendance_rate' => $data['summary']['attendance_rate'],
                    'leave_days' => $data['summary']['leave_days'],
                    'overtime_hours' => $data['summary']['overtime_hours'],
                    'net_pay' => $data['summary']['payroll']['net_pay'],
                ];
            })
            ->values()
            ->all();

        return [
            'period' => $periodStart->format('Y-m'),
            'period_label' => $periodStart->translatedFormat('F Y'),
            'generated_at' => now()->toIso8601String(),
            'filters' => [
                'department_id' => $departmentId,
                'office_location_id' => $officeLocationId,
            ],
            'filter_options' => [
                'departments' => $context['department_options'],
                'office_locations' => $context['office_options'],
            ],
            'summary' => $periodData['summary'],
            'leave_breakdown' => $periodData['leave_breakdown'],
            'overtime_breakdown' => $periodData['overtime_breakdown'],
            'departments' => $this->departmentRows(
                $context['employees'],
                $periodData,
            ),
            'trend' => $trend,
            'insights' => $this->insights($periodData['summary']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(string $period): array
    {
        $report = $this->build($period);

        return [
            'period' => $report['period'],
            'period_label' => $report['period_label'],
            'summary' => $report['summary'],
            'trend' => $report['trend'],
            'insights' => $report['insights'],
        ];
    }

    /**
     * @return array{
     *     employees: Collection<int, array<string, mixed>>,
     *     employee_ids: array<int, int>,
     *     department_options: array<int, array{id: int, name: string}>,
     *     office_options: array<int, array{id: int, name: string}>
     * }
     */
    private function employeeContext(
        ?int $departmentId,
        ?int $officeLocationId,
    ): array {
        $connection = DB::connection('ibco');
        $employees = $connection->table('maklumatpekerja')
            ->where('rcd_enable', 1)
            ->orderBy('nama')
            ->get(['id', 'employeeID', 'nama']);
        $employeeIds = $employees
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $positions = $employeeIds === []
            ? collect()
            : $connection->table('maklumatjawatan as j')
                ->leftJoin('xdepartment as d', 'j.id_department', '=', 'd.id')
                ->where('j.rcd_enable', 1)
                ->whereIn('j.id_pekerja', $employeeIds)
                ->orderByDesc('j.id')
                ->get([
                    'j.id_pekerja as employee_id',
                    'j.id_department as department_id',
                    'd.description as department_name',
                ]);
        $positionsByEmployee = [];

        foreach ($positions as $position) {
            $employeeId = (int) $position->employee_id;

            if (isset($positionsByEmployee[$employeeId])) {
                continue;
            }

            $positionsByEmployee[$employeeId] = [
                'department_id' => $position->department_id === null
                    ? null
                    : (int) $position->department_id,
                'department_name' => $position->department_name
                    ?: 'Tanpa Jabatan',
            ];
        }

        $officeEmployeeIds = null;

        if ($officeLocationId !== null) {
            $officeEmployeeIds = EmployeeUserLink::query()
                ->where('office_location_id', $officeLocationId)
                ->where('is_active', true)
                ->pluck('employee_id')
                ->map(fn ($id) => (int) $id)
                ->flip();
        }

        $resolvedEmployees = $employees
            ->map(function ($employee) use ($positionsByEmployee) {
                $employeeId = (int) $employee->id;
                $position = $positionsByEmployee[$employeeId] ?? [
                    'department_id' => null,
                    'department_name' => 'Tanpa Jabatan',
                ];

                return [
                    'id' => $employeeId,
                    'employee_number' => $employee->employeeID,
                    'name' => $employee->nama,
                    ...$position,
                ];
            })
            ->when(
                $departmentId !== null,
                fn (Collection $items) => $items->where(
                    'department_id',
                    $departmentId,
                ),
            )
            ->when(
                $officeEmployeeIds !== null,
                fn (Collection $items) => $items->filter(
                    fn (array $employee) => $officeEmployeeIds->has(
                        $employee['id'],
                    ),
                ),
            )
            ->values();

        return [
            'employees' => $resolvedEmployees,
            'employee_ids' => $resolvedEmployees->pluck('id')->all(),
            'department_options' => $connection->table('xdepartment')
                ->where('rcd_enable', 1)
                ->orderBy('description')
                ->get(['id', 'description'])
                ->map(fn ($department) => [
                    'id' => (int) $department->id,
                    'name' => $department->description,
                ])
                ->all(),
            'office_options' => OfficeLocation::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (OfficeLocation $office) => [
                    'id' => $office->getKey(),
                    'name' => $office->name,
                ])
                ->all(),
        ];
    }

    /**
     * @param  array<int, int>  $employeeIds
     * @return array<string, mixed>
     */
    private function periodData(
        CarbonImmutable $periodStart,
        array $employeeIds,
    ): array {
        $periodEnd = $periodStart->endOfMonth();
        $workingDaysByEmployee = collect($employeeIds)
            ->mapWithKeys(fn ($employeeId) => [
                (int) $employeeId => $this->scheduleResolver
                    ->scheduledWorkDays(
                        (int) $employeeId,
                        $periodStart,
                        $periodEnd,
                    ),
            ])
            ->all();
        $workingDays = $employeeIds === []
            ? 0
            : (int) round(
                array_sum($workingDaysByEmployee) / count($employeeIds),
            );

        $attendance = GeoAttendanceRecord::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('status', 'active')
            ->whereBetween('attendance_date', [
                $periodStart->toDateString(),
                $periodEnd->toDateString(),
            ])
            ->get([
                'employee_id',
                'attendance_date',
                'clock_in_at',
                'clock_out_at',
                'attendance_day_type',
            ]);
        $workingDayAttendance = $attendance->filter(
            fn (GeoAttendanceRecord $record) => $record->attendance_day_type
                ? $record->attendance_day_type === 'workday'
                : $this->scheduleResolver->isScheduledWorkDay(
                    (int) $record->employee_id,
                    $record->attendance_date,
                ),
        );
        $completedAttendance = $attendance->filter(
            fn (GeoAttendanceRecord $record) => $record->clock_out_at !== null,
        );
        $workedMinutes = $completedAttendance->sum(function (
            GeoAttendanceRecord $record,
        ) {
            return max(
                0,
                $record->clock_in_at->diffInMinutes(
                    $record->clock_out_at,
                    false,
                ),
            );
        });
        $attendanceDaysByEmployee = $attendance
            ->groupBy('employee_id')
            ->map->count()
            ->mapWithKeys(fn ($count, $employeeId) => [
                (int) $employeeId => (int) $count,
            ])
            ->all();

        $approvedLeave = EmployeeLeaveRequest::query()
            ->with('systemLeaveType:id,name')
            ->whereIn('employee_id', $employeeIds)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $periodEnd)
            ->whereDate('end_date', '>=', $periodStart)
            ->get();
        $leaveDaysByEmployee = [];
        $leaveBreakdown = [];

        foreach ($approvedLeave as $leave) {
            $days = $this->leaveDaysInPeriod(
                $leave,
                (int) $leave->employee_id,
                $periodStart,
                $periodEnd,
            );
            $employeeId = (int) $leave->employee_id;
            $leaveDaysByEmployee[$employeeId] = round(
                ($leaveDaysByEmployee[$employeeId] ?? 0) + $days,
                1,
            );
            $label = $leave->systemLeaveType?->name
                ?: ($leave->leave_type_label ?: 'Cuti Lain-lain');
            $leaveBreakdown[$label] = round(
                ($leaveBreakdown[$label] ?? 0) + $days,
                1,
            );
        }

        $approvedOvertime = OvertimeRequest::query()
            ->with('overtimeType:id,name')
            ->whereIn('employee_id', $employeeIds)
            ->where('status', 'approved')
            ->whereBetween('work_date', [
                $periodStart->toDateString(),
                $periodEnd->toDateString(),
            ])
            ->get();
        $overtimeMinutesByEmployee = [];
        $overtimeBreakdown = [];

        foreach ($approvedOvertime as $overtime) {
            $minutes = (int) (
                $overtime->approved_minutes
                ?? $overtime->requested_minutes
            );
            $employeeId = (int) $overtime->employee_id;
            $overtimeMinutesByEmployee[$employeeId] =
                ($overtimeMinutesByEmployee[$employeeId] ?? 0) + $minutes;
            $label = $overtime->overtimeType?->name ?: 'OT Lain-lain';
            $overtimeBreakdown[$label] =
                ($overtimeBreakdown[$label] ?? 0) + $minutes;
        }

        $payrollRun = PayrollRun::query()
            ->whereDate('period_start', $periodStart->toDateString())
            ->first();
        $payrollEntries = $payrollRun
            ? $payrollRun->entries()
                ->with('statutorySnapshot')
                ->whereIn('employee_id', $employeeIds)
                ->get()
            : collect();
        $payrollNetByEmployee = $payrollEntries
            ->mapWithKeys(fn ($entry) => [
                (int) $entry->employee_id => (float) $entry->net_pay,
            ])
            ->all();
        $activeEmployees = count($employeeIds);
        $expectedAttendanceDays = array_sum($workingDaysByEmployee);
        $attendanceRate = $expectedAttendanceDays > 0
            ? min(
                100,
                round(
                    ($workingDayAttendance->count()
                        / $expectedAttendanceDays) * 100,
                    1,
                ),
            )
            : 0;
        $leaveDays = round(array_sum($leaveDaysByEmployee), 1);
        $overtimeMinutes = array_sum($overtimeMinutesByEmployee);
        $pendingLeave = EmployeeLeaveRequest::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('status', 'pending')
            ->whereDate('start_date', '<=', $periodEnd)
            ->whereDate('end_date', '>=', $periodStart)
            ->count();
        $pendingOvertime = OvertimeRequest::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('status', 'pending')
            ->whereBetween('work_date', [
                $periodStart->toDateString(),
                $periodEnd->toDateString(),
            ])
            ->count();

        return [
            'summary' => [
                'active_employees' => $activeEmployees,
                'working_days' => $workingDays,
                'attendance_days' => $attendance->count(),
                'attendance_employees' => $attendance
                    ->pluck('employee_id')
                    ->unique()
                    ->count(),
                'attendance_rate' => $attendanceRate,
                'average_work_hours' => $completedAttendance->count() > 0
                    ? round(
                        $workedMinutes / $completedAttendance->count() / 60,
                        1,
                    )
                    : 0,
                'incomplete_clock_out' => $attendance
                    ->whereNull('clock_out_at')
                    ->count(),
                'leave_requests' => $approvedLeave->count(),
                'leave_days' => $leaveDays,
                'pending_leave' => $pendingLeave,
                'overtime_requests' => $approvedOvertime->count(),
                'overtime_hours' => round($overtimeMinutes / 60, 1),
                'pending_overtime' => $pendingOvertime,
                'pending_actions' => $pendingLeave + $pendingOvertime,
                'payroll' => [
                    'run_id' => $payrollRun?->getKey(),
                    'status' => $payrollRun?->status ?? 'not_generated',
                    'employee_count' => $payrollEntries->count(),
                    'gross_pay' => round(
                        (float) $payrollEntries->sum('gross_pay'),
                        2,
                    ),
                    'deductions' => round(
                        (float) $payrollEntries->sum('total_deductions'),
                        2,
                    ),
                    'net_pay' => round(
                        (float) $payrollEntries->sum('net_pay'),
                        2,
                    ),
                    'employer_contributions' => round(
                        (float) $payrollEntries->sum(
                            fn ($entry) => (float) (
                                $entry->statutorySnapshot
                                    ?->total_employer_contributions
                                ?? 0
                            ),
                        ),
                        2,
                    ),
                ],
            ],
            'attendance_days_by_employee' => $attendanceDaysByEmployee,
            'leave_days_by_employee' => $leaveDaysByEmployee,
            'overtime_minutes_by_employee' => $overtimeMinutesByEmployee,
            'payroll_net_by_employee' => $payrollNetByEmployee,
            'leave_breakdown' => collect($leaveBreakdown)
                ->map(fn ($days, $name) => [
                    'name' => $name,
                    'days' => $days,
                ])
                ->sortByDesc('days')
                ->values()
                ->all(),
            'overtime_breakdown' => collect($overtimeBreakdown)
                ->map(fn ($minutes, $name) => [
                    'name' => $name,
                    'hours' => round($minutes / 60, 1),
                ])
                ->sortByDesc('hours')
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $employees
     * @param  array<string, mixed>  $periodData
     * @return array<int, array<string, mixed>>
     */
    private function departmentRows(
        Collection $employees,
        array $periodData,
    ): array {
        return $employees
            ->groupBy(fn (array $employee) => (string) (
                $employee['department_id'] ?? 'none'
            ))
            ->map(function (Collection $departmentEmployees) use ($periodData) {
                $first = $departmentEmployees->first();
                $employeeIds = $departmentEmployees->pluck('id');

                return [
                    'department_id' => $first['department_id'],
                    'name' => $first['department_name'],
                    'employee_count' => $departmentEmployees->count(),
                    'attendance_days' => $employeeIds->sum(
                        fn ($employeeId) => $periodData[
                            'attendance_days_by_employee'
                        ][$employeeId] ?? 0,
                    ),
                    'leave_days' => round(
                        (float) $employeeIds->sum(
                            fn ($employeeId) => $periodData[
                                'leave_days_by_employee'
                            ][$employeeId] ?? 0,
                        ),
                        1,
                    ),
                    'overtime_hours' => round(
                        (float) $employeeIds->sum(
                            fn ($employeeId) => $periodData[
                                'overtime_minutes_by_employee'
                            ][$employeeId] ?? 0,
                        ) / 60,
                        1,
                    ),
                    'net_pay' => round(
                        (float) $employeeIds->sum(
                            fn ($employeeId) => $periodData[
                                'payroll_net_by_employee'
                            ][$employeeId] ?? 0,
                        ),
                        2,
                    ),
                ];
            })
            ->sortByDesc('employee_count')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<int, array{level: string, title: string, message: string}>
     */
    private function insights(array $summary): array
    {
        $insights = [];

        if ($summary['payroll']['status'] === 'not_generated') {
            $insights[] = [
                'level' => 'warning',
                'title' => 'Payroll belum dijana',
                'message' => 'Jana payroll bulan ini untuk melengkapkan ringkasan kewangan.',
            ];
        } elseif ($summary['payroll']['status'] !== 'finalized') {
            $insights[] = [
                'level' => 'warning',
                'title' => 'Payroll belum dimuktamadkan',
                'message' => 'Payroll masih berstatus '.$summary['payroll']['status'].'.',
            ];
        }

        if ($summary['pending_actions'] > 0) {
            $insights[] = [
                'level' => 'warning',
                'title' => 'Kelulusan masih menunggu',
                'message' => $summary['pending_actions'].' permohonan cuti atau OT memerlukan tindakan.',
            ];
        }

        if ($summary['incomplete_clock_out'] > 0) {
            $insights[] = [
                'level' => 'info',
                'title' => 'Rekod keluar belum lengkap',
                'message' => $summary['incomplete_clock_out'].' rekod kehadiran belum mempunyai waktu keluar.',
            ];
        }

        if ($summary['attendance_rate'] < 85) {
            $insights[] = [
                'level' => 'info',
                'title' => 'Liputan kehadiran perlu disemak',
                'message' => 'Kadar rekod kehadiran bulan ini berada di bawah 85%.',
            ];
        }

        if ($insights === []) {
            $insights[] = [
                'level' => 'success',
                'title' => 'Operasi bulan ini terkawal',
                'message' => 'Tiada isu utama dikesan daripada data kehadiran, kelulusan dan payroll.',
            ];
        }

        return $insights;
    }

    private function leaveDaysInPeriod(
        EmployeeLeaveRequest $leave,
        int $employeeId,
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEnd,
    ): float {
        $start = CarbonImmutable::parse($leave->start_date)
            ->max($periodStart);
        $end = CarbonImmutable::parse($leave->end_date)
            ->min($periodEnd);

        if ($start->gt($end)) {
            return 0;
        }

        if (
            $leave->duration_type !== 'full_day'
            && $start->isSameDay($end)
        ) {
            return $this->scheduleResolver->isScheduledWorkDay(
                $employeeId,
                $start,
            ) ? .5 : 0;
        }

        return (float) $this->scheduleResolver->scheduledWorkDays(
            $employeeId,
            $start,
            $end,
        );
    }
}
