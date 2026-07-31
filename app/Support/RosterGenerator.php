<?php

namespace App\Support;

use App\Models\PublicHoliday;
use App\Models\RosterEntry;
use App\Models\RosterPeriod;
use App\Models\ScheduleAssignment;
use App\Models\ShiftTemplate;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RosterGenerator
{
    public function __construct(
        private readonly WorkScheduleResolver $resolver,
    ) {}

    /**
     * @param  array<int, array{
     *     employee_id: int,
     *     user_id: int|null,
     *     department_id: int|null,
     *     office_location_id: int|null
     * }>  $employees
     */
    public function generate(
        RosterPeriod $period,
        array $employees,
        User $actor,
    ): RosterPeriod {
        if ($period->status !== 'draft') {
            throw ValidationException::withMessages([
                'roster' => 'Hanya roster berstatus Draf boleh dijana semula.',
            ]);
        }

        return DB::transaction(function () use ($period, $employees, $actor) {
            $employeeIds = array_column($employees, 'employee_id');
            RosterEntry::query()
                ->where('roster_period_id', $period->getKey())
                ->whereNotIn('employee_id', $employeeIds)
                ->delete();
            $assignments = ScheduleAssignment::query()
                ->with('shiftTemplate')
                ->where('is_active', true)
                ->whereDate('effective_from', '<=', $period->period_end)
                ->where(function ($query) use ($period) {
                    $query->whereNull('effective_to')
                        ->orWhereDate('effective_to', '>=', $period->period_start);
                })
                ->get();
            $holidays = PublicHoliday::query()
                ->where('is_active', true)
                ->whereBetween('holiday_date', [
                    $period->period_start,
                    $period->period_end,
                ])
                ->pluck('holiday_date')
                ->map(fn ($date) => CarbonImmutable::parse($date)->toDateString())
                ->flip()
                ->all();
            $defaultTemplate = ShiftTemplate::query()
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->first();

            foreach ($employees as $employee) {
                for (
                    $date = $period->period_start;
                    $date->lte($period->period_end);
                    $date = $date->addDay()
                ) {
                    $existing = RosterEntry::query()
                        ->where('employee_id', $employee['employee_id'])
                        ->whereDate('work_date', $date)
                        ->first();

                    if ($existing?->source === 'manual') {
                        continue;
                    }

                    $schedule = $this->resolver->resolveForGeneration(
                        $employee['employee_id'],
                        $employee['department_id'],
                        $employee['office_location_id'],
                        $date,
                        $assignments,
                        $holidays,
                        $defaultTemplate,
                    );

                    RosterEntry::query()->updateOrCreate(
                        [
                            'employee_id' => $employee['employee_id'],
                            'work_date' => $date->toDateString(),
                        ],
                        [
                            'roster_period_id' => $period->getKey(),
                            'user_id' => $employee['user_id'],
                            'department_id' => $employee['department_id'],
                            'office_location_id' => $employee[
                                'office_location_id'
                            ],
                            'shift_template_id' => $schedule[
                                'shift_template_id'
                            ],
                            'day_type' => $schedule['day_type'],
                            'scheduled_start_at' => $schedule[
                                'scheduled_start_at'
                            ],
                            'scheduled_end_at' => $schedule['scheduled_end_at'],
                            'break_minutes' => $schedule['break_minutes'],
                            'source' => 'generated',
                            'updated_by' => $actor->getAuthIdentifier(),
                        ],
                    );
                }
            }

            $period->update([
                'updated_by' => $actor->getAuthIdentifier(),
            ]);

            return $period->fresh();
        });
    }
}
