<?php

namespace App\Support;

use App\Models\ClaimLimitOverride;
use App\Models\ClaimRequest;
use App\Models\ClaimType;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class ClaimLimitResolver
{
    /**
     * @return array{max_per_claim: float|null, monthly_limit: float|null, annual_limit: float|null, source: string}
     */
    public function resolve(
        ClaimType $type,
        int $employeeId,
        ?int $positionId,
    ): array {
        $overrides = ClaimLimitOverride::query()
            ->where('claim_type_id', $type->getKey())
            ->where('is_active', true)
            ->where(function ($query) use ($employeeId, $positionId) {
                $query->where(function ($query) use ($employeeId) {
                    $query->where('scope_type', 'employee')
                        ->where('scope_id', $employeeId);
                });

                if ($positionId !== null) {
                    $query->orWhere(function ($query) use ($positionId) {
                        $query->where('scope_type', 'position')
                            ->where('scope_id', $positionId);
                    });
                }
            })
            ->get()
            ->keyBy('scope_type');
        $override = $overrides->get('employee')
            ?? $overrides->get('position');

        return [
            'max_per_claim' => $this->nullableFloat(
                $override?->max_per_claim ?? $type->max_per_claim,
            ),
            'monthly_limit' => $this->nullableFloat(
                $override?->monthly_limit ?? $type->monthly_limit,
            ),
            'annual_limit' => $this->nullableFloat(
                $override?->annual_limit ?? $type->annual_limit,
            ),
            'source' => $override?->scope_type ?? 'type',
        ];
    }

    /**
     * @return array{month_used: float, year_used: float}
     */
    public function usage(
        int $employeeId,
        int $claimTypeId,
        CarbonInterface $expenseDate,
        ?int $exceptRequestId = null,
    ): array {
        $base = ClaimRequest::query()
            ->where('employee_id', $employeeId)
            ->where('claim_type_id', $claimTypeId)
            ->whereIn('status', ['pending', 'approved'])
            ->when(
                $exceptRequestId !== null,
                fn ($query) => $query->where('id', '!=', $exceptRequestId),
            );
        $amountSql = 'COALESCE(approved_amount, requested_amount)';

        return [
            'month_used' => round((float) (clone $base)
                ->whereYear('expense_date', $expenseDate->year)
                ->whereMonth('expense_date', $expenseDate->month)
                ->selectRaw("COALESCE(SUM({$amountSql}), 0) as aggregate")
                ->value('aggregate'), 2),
            'year_used' => round((float) (clone $base)
                ->whereYear('expense_date', $expenseDate->year)
                ->selectRaw("COALESCE(SUM({$amountSql}), 0) as aggregate")
                ->value('aggregate'), 2),
        ];
    }

    /**
     * @param  array{max_per_claim: float|null, monthly_limit: float|null, annual_limit: float|null, source: string}  $limits
     * @param  array{month_used: float, year_used: float}  $usage
     */
    public function assertAmountAllowed(
        float $amount,
        array $limits,
        array $usage,
        string $field = 'requested_amount',
    ): void {
        if (
            $limits['max_per_claim'] !== null
            && $amount > $limits['max_per_claim']
        ) {
            throw ValidationException::withMessages([
                $field => 'Amaun melebihi had setiap tuntutan RM'
                    .number_format($limits['max_per_claim'], 2).'.',
            ]);
        }

        if (
            $limits['monthly_limit'] !== null
            && $usage['month_used'] + $amount > $limits['monthly_limit']
        ) {
            throw ValidationException::withMessages([
                $field => 'Had bulanan RM'
                    .number_format($limits['monthly_limit'], 2)
                    .' telah dicapai. Penggunaan semasa RM'
                    .number_format($usage['month_used'], 2).'.',
            ]);
        }

        if (
            $limits['annual_limit'] !== null
            && $usage['year_used'] + $amount > $limits['annual_limit']
        ) {
            throw ValidationException::withMessages([
                $field => 'Had tahunan RM'
                    .number_format($limits['annual_limit'], 2)
                    .' telah dicapai. Penggunaan semasa RM'
                    .number_format($usage['year_used'], 2).'.',
            ]);
        }
    }

    private function nullableFloat(mixed $value): ?float
    {
        return $value === null ? null : round((float) $value, 2);
    }
}
