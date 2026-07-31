<?php

namespace App\Support;

use App\Models\PublicHoliday;
use App\Models\RosterEntry;
use App\Models\ScheduleAssignment;
use App\Models\ShiftTemplate;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WorkScheduleResolver
{
    /**
     * @return array<string, mixed>
     */
    public function resolve(
        int $employeeId,
        CarbonInterface|string $date,
        ?int $departmentId = null,
        ?int $officeLocationId = null,
        bool $officialRosterOnly = true,
    ): array {
        $date = CarbonImmutable::parse($date)->startOfDay();
        $entry = RosterEntry::query()
            ->with(['period:id,status', 'shiftTemplate'])
            ->where('employee_id', $employeeId)
            ->whereDate('work_date', $date)
            ->when(
                $officialRosterOnly,
                fn (Builder $query) => $query->whereHas(
                    'period',
                    fn (Builder $period) => $period->whereIn(
                        'status',
                        ['published', 'locked'],
                    ),
                ),
            )
            ->first();

        if ($entry) {
            return $this->fromRosterEntry($entry);
        }

        $departmentId ??= $this->departmentId($employeeId);
        $holiday = PublicHoliday::query()
            ->where('is_active', true)
            ->whereDate('holiday_date', $date)
            ->first();
        $assignment = $this->assignmentFor(
            $employeeId,
            $departmentId,
            $officeLocationId,
            $date,
        );
        $template = $assignment?->shiftTemplate
            ?? ShiftTemplate::query()
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->first();

        return $this->fromTemplate(
            $template,
            $date,
            $holiday !== null,
            $assignment ? 'assignment' : 'default',
        );
    }

    /**
     * @param  Collection<int, ScheduleAssignment>  $assignments
     * @param  array<string, bool>  $holidays
     * @return array<string, mixed>
     */
    public function resolveForGeneration(
        int $employeeId,
        ?int $departmentId,
        ?int $officeLocationId,
        CarbonInterface|string $date,
        Collection $assignments,
        array $holidays,
        ?ShiftTemplate $defaultTemplate,
    ): array {
        $date = CarbonImmutable::parse($date)->startOfDay();
        $assignment = $this->selectAssignment(
            $assignments,
            $employeeId,
            $departmentId,
            $officeLocationId,
            $date,
        );

        return $this->fromTemplate(
            $assignment?->shiftTemplate ?? $defaultTemplate,
            $date,
            isset($holidays[$date->toDateString()]),
            $assignment ? 'assignment' : 'default',
        );
    }

    public function isScheduledWorkDay(
        int $employeeId,
        CarbonInterface|string $date,
    ): bool {
        return $this->resolve($employeeId, $date)['day_type'] === 'workday';
    }

    public function scheduledWorkDays(
        int $employeeId,
        CarbonInterface|string $start,
        CarbonInterface|string $end,
    ): int {
        $start = CarbonImmutable::parse($start)->startOfDay();
        $end = CarbonImmutable::parse($end)->startOfDay();
        $days = 0;
        $officialRoster = RosterEntry::query()
            ->where('employee_id', $employeeId)
            ->whereBetween('work_date', [
                $start->toDateString(),
                $end->toDateString(),
            ])
            ->whereHas(
                'period',
                fn (Builder $query) => $query->whereIn(
                    'status',
                    ['published', 'locked'],
                ),
            )
            ->get(['work_date', 'day_type'])
            ->mapWithKeys(fn (RosterEntry $entry) => [
                $entry->work_date->toDateString() => $entry->day_type,
            ]);

        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            $dayType = $officialRoster->get($date->toDateString());

            if (
                $dayType === 'workday'
                || (
                    $dayType === null
                    && $this->isScheduledWorkDay($employeeId, $date)
                )
            ) {
                $days++;
            }
        }

        return $days;
    }

    /**
     * @return array{
     *     roster_entry_id: int|null,
     *     scheduled_start_at: CarbonInterface|null,
     *     scheduled_end_at: CarbonInterface|null,
     *     scheduled_minutes: int,
     *     late_minutes: int,
     *     early_departure_minutes: int,
     *     attendance_day_type: string
     * }
     */
    public function attendanceSnapshot(
        int $employeeId,
        CarbonInterface|string $date,
        CarbonInterface $clockIn,
        ?CarbonInterface $clockOut = null,
        ?int $departmentId = null,
        ?int $officeLocationId = null,
    ): array {
        $schedule = $this->resolve(
            $employeeId,
            $date,
            $departmentId,
            $officeLocationId,
        );
        $scheduledStart = $schedule['scheduled_start_at'];
        $scheduledEnd = $schedule['scheduled_end_at'];
        $lateMinutes = 0;
        $earlyMinutes = 0;

        if ($schedule['day_type'] === 'workday' && $scheduledStart) {
            $lateThreshold = $scheduledStart->addMinutes(
                (int) $schedule['grace_minutes'],
            );
            $lateMinutes = $clockIn->greaterThan($lateThreshold)
                ? $lateThreshold->diffInMinutes($clockIn)
                : 0;
        }

        if (
            $schedule['day_type'] === 'workday'
            && $scheduledEnd
            && $clockOut
        ) {
            $earlyThreshold = $scheduledEnd->subMinutes(
                (int) $schedule['early_departure_grace_minutes'],
            );
            $earlyMinutes = $clockOut->lessThan($earlyThreshold)
                ? $clockOut->diffInMinutes($earlyThreshold)
                : 0;
        }

        return [
            'roster_entry_id' => $schedule['roster_entry_id'],
            'scheduled_start_at' => $scheduledStart,
            'scheduled_end_at' => $scheduledEnd,
            'scheduled_minutes' => (int) $schedule['scheduled_minutes'],
            'late_minutes' => $lateMinutes,
            'early_departure_minutes' => $earlyMinutes,
            'attendance_day_type' => $schedule['day_type'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fromRosterEntry(RosterEntry $entry): array
    {
        $template = $entry->shiftTemplate;
        $scheduledMinutes = $entry->scheduled_start_at && $entry->scheduled_end_at
            ? (int) max(
                0,
                $entry->scheduled_start_at->diffInMinutes(
                    $entry->scheduled_end_at,
                ) - $entry->break_minutes,
            )
            : 0;

        return [
            'roster_entry_id' => $entry->getKey(),
            'shift_template_id' => $entry->shift_template_id,
            'shift_name' => $template?->name,
            'day_type' => $entry->day_type,
            'scheduled_start_at' => $entry->scheduled_start_at,
            'scheduled_end_at' => $entry->scheduled_end_at,
            'scheduled_minutes' => $scheduledMinutes,
            'break_minutes' => $entry->break_minutes,
            'grace_minutes' => (int) ($template?->grace_minutes ?? 0),
            'early_departure_grace_minutes' => (int) (
                $template?->early_departure_grace_minutes ?? 0
            ),
            'source' => 'roster',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fromTemplate(
        ?ShiftTemplate $template,
        CarbonImmutable $date,
        bool $isHoliday,
        string $source,
    ): array {
        if ($isHoliday) {
            return $this->nonWorkingDay('public_holiday', $source, $template);
        }

        if (! $template) {
            return $this->nonWorkingDay('off', $source, null);
        }

        $workDays = array_map('intval', $template->work_days ?? []);

        if (! in_array($date->dayOfWeekIso, $workDays, true)) {
            return $this->nonWorkingDay('rest_day', $source, $template);
        }

        $start = CarbonImmutable::parse(
            "{$date->toDateString()} {$template->start_time}",
        );
        $end = CarbonImmutable::parse(
            "{$date->toDateString()} {$template->end_time}",
        );

        if ($template->crosses_midnight || $end->lessThanOrEqualTo($start)) {
            $end = $end->addDay();
        }

        return [
            'roster_entry_id' => null,
            'shift_template_id' => $template->getKey(),
            'shift_name' => $template->name,
            'day_type' => 'workday',
            'scheduled_start_at' => $start,
            'scheduled_end_at' => $end,
            'scheduled_minutes' => (int) max(
                0,
                $start->diffInMinutes($end) - $template->break_minutes,
            ),
            'break_minutes' => $template->break_minutes,
            'grace_minutes' => $template->grace_minutes,
            'early_departure_grace_minutes' => $template
                ->early_departure_grace_minutes,
            'source' => $source,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function nonWorkingDay(
        string $dayType,
        string $source,
        ?ShiftTemplate $template,
    ): array {
        return [
            'roster_entry_id' => null,
            'shift_template_id' => $template?->getKey(),
            'shift_name' => $template?->name,
            'day_type' => $dayType,
            'scheduled_start_at' => null,
            'scheduled_end_at' => null,
            'scheduled_minutes' => 0,
            'break_minutes' => 0,
            'grace_minutes' => 0,
            'early_departure_grace_minutes' => 0,
            'source' => $source,
        ];
    }

    private function assignmentFor(
        int $employeeId,
        ?int $departmentId,
        ?int $officeLocationId,
        CarbonInterface $date,
    ): ?ScheduleAssignment {
        $assignments = ScheduleAssignment::query()
            ->with('shiftTemplate')
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $date)
            ->where(function (Builder $query) use ($date) {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $date);
            })
            ->where(function (Builder $query) use (
                $employeeId,
                $departmentId,
                $officeLocationId,
            ) {
                $query->where(function (Builder $scope) use ($employeeId) {
                    $scope->where('scope_type', 'employee')
                        ->where('employee_id', $employeeId);
                });

                if ($departmentId !== null) {
                    $query->orWhere(function (Builder $scope) use ($departmentId) {
                        $scope->where('scope_type', 'department')
                            ->where('department_id', $departmentId);
                    });
                }

                if ($officeLocationId !== null) {
                    $query->orWhere(function (Builder $scope) use ($officeLocationId) {
                        $scope->where('scope_type', 'office')
                            ->where('office_location_id', $officeLocationId);
                    });
                }
            })
            ->get();

        return $this->selectAssignment(
            $assignments,
            $employeeId,
            $departmentId,
            $officeLocationId,
            $date,
        );
    }

    /**
     * @param  Collection<int, ScheduleAssignment>  $assignments
     */
    private function selectAssignment(
        Collection $assignments,
        int $employeeId,
        ?int $departmentId,
        ?int $officeLocationId,
        CarbonInterface $date,
    ): ?ScheduleAssignment {
        return $assignments
            ->filter(function (ScheduleAssignment $assignment) use (
                $employeeId,
                $departmentId,
                $officeLocationId,
                $date,
            ) {
                if (
                    ! $assignment->is_active
                    || ! $assignment->shiftTemplate?->is_active
                    || $assignment->effective_from->greaterThan($date)
                    || (
                        $assignment->effective_to
                        && $assignment->effective_to->lessThan($date)
                    )
                ) {
                    return false;
                }

                return match ($assignment->scope_type) {
                    'employee' => $assignment->employee_id === $employeeId,
                    'department' => $departmentId !== null
                        && $assignment->department_id === $departmentId,
                    'office' => $officeLocationId !== null
                        && $assignment->office_location_id === $officeLocationId,
                    default => false,
                };
            })
            ->sortByDesc(fn (ScheduleAssignment $assignment) => (
                match ($assignment->scope_type) {
                    'employee' => 3000,
                    'department' => 2000,
                    'office' => 1000,
                    default => 0,
                }
                + $assignment->priority
            ))
            ->first();
    }

    private function departmentId(int $employeeId): ?int
    {
        $departmentId = DB::connection('ibco')
            ->table('maklumatjawatan')
            ->where('id_pekerja', $employeeId)
            ->where('rcd_enable', 1)
            ->orderByDesc('id')
            ->value('id_department');

        return $departmentId === null ? null : (int) $departmentId;
    }
}
