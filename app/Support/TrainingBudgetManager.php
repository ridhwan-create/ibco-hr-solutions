<?php

namespace App\Support;

use App\Models\TrainingBudget;
use App\Models\TrainingRequest;
use Illuminate\Validation\ValidationException;

class TrainingBudgetManager
{
    /**
     * @return array{allocated: float, used: float, available: float, budget_id: int|null}
     */
    public function summary(int $year, ?int $departmentId): array
    {
        $budget = TrainingBudget::query()
            ->where('year', $year)
            ->where(fn ($query) => $query
                ->where('department_id', $departmentId)
                ->orWhereNull('department_id'))
            ->orderByRaw('department_id is null')
            ->first();
        $used = (float) TrainingRequest::query()
            ->whereIn('status', ['approved', 'completed'])
            ->where('budget_year', $year)
            ->when(
                $budget?->department_id !== null,
                fn ($query) => $query->where('department_id', $departmentId),
            )
            ->sum('approved_cost');
        $allocated = (float) ($budget?->allocated_amount ?? 0);

        return [
            'allocated' => round($allocated, 2),
            'used' => round($used, 2),
            'available' => round(max(0, $allocated - $used), 2),
            'budget_id' => $budget?->getKey(),
        ];
    }

    public function assertAvailable(
        int $year,
        ?int $departmentId,
        float $amount,
    ): void {
        $budget = TrainingBudget::query()
            ->where('year', $year)
            ->where(fn ($query) => $query
                ->where('department_id', $departmentId)
                ->orWhereNull('department_id'))
            ->orderByRaw('department_id is null')
            ->lockForUpdate()
            ->first();

        if (! $budget) {
            throw ValidationException::withMessages([
                'approved_cost' => "Bajet latihan bagi tahun {$year} belum ditetapkan.",
            ]);
        }

        $summary = $this->summary($year, $departmentId);

        if ($amount > $summary['available']) {
            throw ValidationException::withMessages([
                'approved_cost' => 'Baki bajet latihan tidak mencukupi. Baki semasa RM '
                    .number_format($summary['available'], 2).'.',
            ]);
        }
    }
}
