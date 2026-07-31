<?php

namespace App\Support;

use App\Models\EmployeeStatutoryProfile;
use App\Models\PayrollEntry;
use App\Models\PayrollEntryItem;
use App\Models\PayrollStatutorySnapshot;
use App\Models\StatutorySetting;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class StatutoryCalculator
{
    /**
     * @param  array<string, float|string|null>|null  $override
     */
    public function apply(
        PayrollEntry $entry,
        CarbonInterface $periodStart,
        ?User $actor = null,
        ?array $override = null,
    ): PayrollStatutorySnapshot {
        $settings = StatutorySetting::query()->firstOrCreate(['id' => 1]);
        $legacy = $this->legacyEmployee($entry->employee_id);
        $profile = EmployeeStatutoryProfile::query()
            ->where('employee_id', $entry->employee_id)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $periodStart)
            ->first();
        $resolved = $this->resolvedProfile($profile, $legacy, $periodStart);
        $wages = $this->wageBases($entry);

        [$kwspEmployee, $kwspEmployer] = $periodStart->gte(
            $settings->kwsp_effective_from,
        )
            ? $this->kwsp(
                $wages['epf'],
                $resolved['kwsp_category'],
                $settings,
            )
            : [0.0, 0.0];
        [$socsoEmployee, $socsoEmployer] = $periodStart->gte(
            $settings->socso_effective_from,
        )
            ? $this->socso(
                $wages['socso'],
                $resolved['socso_category'],
                $settings,
            )
            : [0.0, 0.0];
        [$eisEmployee, $eisEmployer] = (
            $resolved['eis_enabled']
            && $periodStart->gte($settings->eis_effective_from)
        )
            ? $this->eis($wages['eis'], $settings)
            : [0.0, 0.0];
        $pcb = $resolved['pcb_method'] === 'fixed'
            ? round((float) $resolved['pcb_monthly_amount'], 2)
            : 0.0;

        $values = [
            'kwsp_employee' => $kwspEmployee,
            'kwsp_employer' => $kwspEmployer,
            'socso_employee' => $socsoEmployee,
            'socso_employer' => $socsoEmployer,
            'eis_employee' => $eisEmployee,
            'eis_employer' => $eisEmployer,
            'pcb' => $pcb,
        ];

        if ($override !== null) {
            foreach (array_keys($values) as $key) {
                if (array_key_exists($key, $override)) {
                    $values[$key] = round(max(0, (float) $override[$key]), 2);
                }
            }
        }

        $totalEmployee = round(
            $values['kwsp_employee']
            + $values['socso_employee']
            + $values['eis_employee']
            + $values['pcb'],
            2,
        );
        $totalEmployer = round(
            $values['kwsp_employer']
            + $values['socso_employer']
            + $values['eis_employer'],
            2,
        );
        $snapshot = PayrollStatutorySnapshot::query()->updateOrCreate(
            ['payroll_entry_id' => $entry->getKey()],
            [
                'employee_statutory_profile_id' => $profile?->getKey(),
                'kwsp_category' => $resolved['kwsp_category'],
                'socso_category' => $resolved['socso_category'],
                'eis_enabled' => $resolved['eis_enabled'],
                'epf_wages' => $wages['epf'],
                'socso_wages' => $wages['socso'],
                'eis_wages' => $wages['eis'],
                'pcb_wages' => $wages['pcb'],
                ...$values,
                'total_employee_deductions' => $totalEmployee,
                'total_employer_contributions' => $totalEmployer,
                'epf_number' => $resolved['epf_number'],
                'socso_number' => $resolved['socso_number'],
                'tax_number' => $resolved['tax_number'],
                'rate_version' => sprintf(
                    'KWSP %s · PERKESO/SKBBK %s · EIS %s · PCB %d',
                    $settings->kwsp_effective_from->format('Y-m'),
                    $settings->socso_effective_from->format('Y-m'),
                    $settings->eis_effective_from->format('Y-m'),
                    $settings->pcb_tax_year,
                ),
                'calculation_details' => [
                    'kwsp_effective_from' => $settings->kwsp_effective_from->toDateString(),
                    'socso_effective_from' => $settings->socso_effective_from->toDateString(),
                    'eis_effective_from' => $settings->eis_effective_from->toDateString(),
                    'pcb_method' => $resolved['pcb_method'],
                    'profile_source' => $profile ? 'configured' : 'inferred',
                ],
                'is_overridden' => $override !== null,
                'override_notes' => $override['notes'] ?? null,
                'overridden_by' => $override !== null
                    ? $actor?->getAuthIdentifier()
                    : null,
                'calculated_at' => now(),
            ],
        );

        $entry->items()->where('category', 'statutory')->delete();
        $this->deductionItem($entry, $snapshot, 'KWSP', 'KWSP (Pekerja)', $values['kwsp_employee']);
        $this->deductionItem($entry, $snapshot, 'PERKESO', 'PERKESO / SKBBK (Pekerja)', $values['socso_employee']);
        $this->deductionItem($entry, $snapshot, 'EIS', 'EIS (Pekerja)', $values['eis_employee']);
        $this->deductionItem($entry, $snapshot, 'PCB', 'Potongan Cukai Bulanan (PCB)', $values['pcb']);

        return $snapshot->fresh();
    }

    /**
     * @return array{epf: float, socso: float, eis: float, pcb: float}
     */
    private function wageBases(PayrollEntry $entry): array
    {
        $items = $entry->items()
            ->where('category', '!=', 'statutory')
            ->get();
        $sum = function (string $flag) use ($items): float {
            return round(max(0, (float) $items
                ->filter(fn (PayrollEntryItem $item) => $item->{$flag})
                ->sum(fn (PayrollEntryItem $item) => (
                    $item->type === 'deduction' ? -1 : 1
                ) * (float) $item->amount)), 2);
        };

        return [
            'epf' => $sum('is_epf_wage'),
            'socso' => $sum('is_socso_wage'),
            'eis' => $sum('is_eis_wage'),
            'pcb' => $sum('is_pcb_wage'),
        ];
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function kwsp(
        float $wages,
        string $category,
        StatutorySetting $settings,
    ): array {
        if ($wages <= 0 || $category === 'exempt') {
            return [0.0, 0.0];
        }

        [$employeeRate, $employerRate] = match ($category) {
            'citizen_60_plus' => [
                (float) $settings->kwsp_age60_employee_rate,
                (float) $settings->kwsp_age60_employer_rate,
            ],
            'pr_60_plus' => [
                (float) $settings->kwsp_pr_age60_employee_rate,
                (float) $settings->kwsp_pr_age60_employer_rate,
            ],
            'non_malaysian' => [
                (float) $settings->kwsp_foreign_employee_rate,
                (float) $settings->kwsp_foreign_employer_rate,
            ],
            default => [
                (float) $settings->kwsp_employee_rate,
                $wages <= (float) $settings->kwsp_employer_threshold
                    ? (float) $settings->kwsp_employer_rate_low
                    : (float) $settings->kwsp_employer_rate_high,
            ],
        };

        if ($category === 'non_malaysian') {
            return [
                (float) ceil($wages * $employeeRate / 100),
                (float) ceil($wages * $employerRate / 100),
            ];
        }

        if ($wages <= (float) $settings->kwsp_table_limit) {
            $band = $wages <= (float) $settings->kwsp_employer_threshold
                ? 20
                : 100;
            $referenceWages = ceil($wages / $band) * $band;

            return [
                (float) ceil($referenceWages * $employeeRate / 100),
                (float) ceil($referenceWages * $employerRate / 100),
            ];
        }

        return [
            round($wages * $employeeRate / 100, 2),
            round($wages * $employerRate / 100, 2),
        ];
    }

    /**
     * Current First/Second Category table including SKBBK from June 2026.
     *
     * @return array{0: float, 1: float}
     */
    private function socso(
        float $wages,
        string $category,
        StatutorySetting $settings,
    ): array {
        if ($wages <= 0 || $category === 'exempt') {
            return [0.0, 0.0];
        }
        $wages = min($wages, (float) $settings->socso_wage_ceiling);

        if (! $this->usesOfficialSocsoRates($settings)) {
            $employerRate = $category === 'second'
                ? (float) $settings->socso_second_employer_rate
                : (float) $settings->socso_first_employer_rate;
            $employeeRate = (float) $settings->socso_skbbk_employee_rate
                + ($category === 'first'
                    ? (float) $settings->socso_first_employee_rate
                    : 0);

            return [
                $this->nearestFiveSen($wages * $employeeRate / 100),
                $this->nearestFiveSen($wages * $employerRate / 100),
            ];
        }

        $lowerBands = [
            [30, .40, .10, .20, .30],
            [50, .70, .20, .30, .50],
            [70, 1.10, .30, .50, .80],
            [100, 1.50, .40, .65, 1.10],
            [140, 2.10, .60, .90, 1.50],
            [200, 2.95, .85, 1.25, 2.10],
        ];

        foreach ($lowerBands as [$upper, $firstEmployer, $invalidity, $skbbk, $secondEmployer]) {
            if ($wages <= $upper) {
                return $category === 'second'
                    ? [$skbbk, $secondEmployer]
                    : [$invalidity + $skbbk, $firstEmployer];
            }
        }

        $index = min(57, (int) floor(($wages - 200.000001) / 100));
        $oddAdjustment = $index % 2 === 1 ? 5 : 0;
        $firstEmployer = (435 + ($index * 175) + $oddAdjustment) / 100;
        $invalidity = (125 + ($index * 50)) / 100;
        $skbbk = (185 + ($index * 75) + $oddAdjustment) / 100;
        $secondEmployer = (310 + ($index * 125) + $oddAdjustment) / 100;

        return $category === 'second'
            ? [$skbbk, $secondEmployer]
            : [$invalidity + $skbbk, $firstEmployer];
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function eis(float $wages, StatutorySetting $settings): array
    {
        if ($wages <= 0) {
            return [0.0, 0.0];
        }
        $wages = min($wages, (float) $settings->eis_wage_ceiling);

        if (
            (float) $settings->eis_employee_rate !== .2
            || (float) $settings->eis_employer_rate !== .2
        ) {
            return [
                $this->nearestFiveSen(
                    $wages * (float) $settings->eis_employee_rate / 100,
                ),
                $this->nearestFiveSen(
                    $wages * (float) $settings->eis_employer_rate / 100,
                ),
            ];
        }

        $lowerBands = [
            [30, .05],
            [50, .10],
            [70, .10],
            [100, .20],
            [140, .20],
            [200, .30],
        ];

        foreach ($lowerBands as [$upper, $amount]) {
            if ($wages <= $upper) {
                return [$amount, $amount];
            }
        }

        $index = min(57, (int) floor(($wages - 200.000001) / 100));
        $amount = round(.50 + ($index * .20), 2);

        return [$amount, $amount];
    }

    private function usesOfficialSocsoRates(StatutorySetting $settings): bool
    {
        return (float) $settings->socso_wage_ceiling === 6000.0
            && (float) $settings->socso_first_employer_rate === 1.75
            && (float) $settings->socso_first_employee_rate === .5
            && (float) $settings->socso_skbbk_employee_rate === .75
            && (float) $settings->socso_second_employer_rate === 1.25;
    }

    private function nearestFiveSen(float $amount): float
    {
        return round(round($amount * 20) / 20, 2);
    }

    /**
     * @param  array<string, mixed>  $legacy
     * @return array<string, mixed>
     */
    private function resolvedProfile(
        ?EmployeeStatutoryProfile $profile,
        array $legacy,
        CarbonInterface $periodStart,
    ): array {
        if ($profile) {
            return [
                'kwsp_category' => $profile->kwsp_category,
                'socso_category' => $profile->socso_category,
                'eis_enabled' => $profile->eis_enabled,
                'pcb_method' => $profile->pcb_method,
                'pcb_monthly_amount' => $profile->pcb_monthly_amount,
                'epf_number' => $profile->epf_number ?: $legacy['epf_number'],
                'socso_number' => $profile->socso_number ?: $legacy['socso_number'],
                'tax_number' => $profile->tax_number,
            ];
        }

        $nationality = strtolower(trim((string) ($legacy['nationality'] ?? '')));
        $isMalaysian = $nationality === ''
            || str_contains($nationality, 'malaysia');
        $age = $legacy['birth_date']
            ? (int) Carbon::parse($legacy['birth_date'])
                ->diffInYears($periodStart->copy()->endOfMonth())
            : 30;
        $is60Plus = $age >= 60;

        return [
            'kwsp_category' => $isMalaysian
                ? ($is60Plus ? 'citizen_60_plus' : 'citizen_below_60')
                : 'non_malaysian',
            'socso_category' => $is60Plus ? 'second' : 'first',
            'eis_enabled' => $isMalaysian && ! $is60Plus,
            'pcb_method' => 'fixed',
            'pcb_monthly_amount' => 0,
            'epf_number' => $legacy['epf_number'],
            'socso_number' => $legacy['socso_number'],
            'tax_number' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function legacyEmployee(int $employeeId): array
    {
        $activeJob = DB::connection('ibco')
            ->table('maklumatjawatan')
            ->where('id_pekerja', $employeeId)
            ->where('rcd_enable', 1)
            ->orderByDesc('id')
            ->first(['noepf', 'nosocso']);
        $employee = DB::connection('ibco')
            ->table('maklumatpekerja')
            ->where('id', $employeeId)
            ->first(['tarikhlahir', 'kewarganegaraan']);

        return [
            'birth_date' => $employee?->tarikhlahir,
            'nationality' => $employee?->kewarganegaraan,
            'epf_number' => $activeJob?->noepf,
            'socso_number' => $activeJob?->nosocso,
        ];
    }

    private function deductionItem(
        PayrollEntry $entry,
        PayrollStatutorySnapshot $snapshot,
        string $code,
        string $name,
        float $amount,
    ): void {
        if ($amount <= 0) {
            return;
        }

        $entry->items()->create([
            'code' => $code,
            'name' => $name,
            'type' => 'deduction',
            'category' => 'statutory',
            'quantity' => 1,
            'rate' => $amount,
            'multiplier' => 1,
            'amount' => $amount,
            'source_type' => PayrollStatutorySnapshot::class,
            'source_id' => $snapshot->getKey(),
            'is_manual' => false,
            'is_epf_wage' => false,
            'is_socso_wage' => false,
            'is_eis_wage' => false,
            'is_pcb_wage' => false,
        ]);
    }
}
