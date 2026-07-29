<?php

namespace App\Support;

use App\Models\EmployeeLeaveRequest;
use App\Models\LeaveBalanceTransaction;
use App\Models\LeaveEntitlement;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class LeaveBalanceManager
{
    /**
     * @return array{entitled: float, carry_forward: float, adjustment: float, transactions: float, balance: float}
     */
    public function summary(int $employeeId, LeaveType $leaveType, int $year): array
    {
        $entitlement = LeaveEntitlement::query()
            ->where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveType->getKey())
            ->where('year', $year)
            ->first();

        $entitled = (float) ($entitlement?->entitled_days
            ?? $leaveType->default_entitlement_days);
        $carryForward = (float) ($entitlement?->carry_forward_days ?? 0);
        $adjustment = (float) ($entitlement?->adjustment_days ?? 0);
        $transactions = $entitlement
            ? (float) $entitlement->transactions()->sum('days')
            : 0.0;

        return [
            'entitled' => $entitled,
            'carry_forward' => $carryForward,
            'adjustment' => $adjustment,
            'transactions' => $transactions,
            'balance' => $entitled + $carryForward + $adjustment + $transactions,
        ];
    }

    public function deduct(EmployeeLeaveRequest $leave, User $actor): void
    {
        $leaveType = $leave->systemLeaveType;

        if (! $leaveType || ! $leaveType->deduct_balance) {
            return;
        }

        $entitlement = LeaveEntitlement::query()->firstOrCreate(
            [
                'employee_id' => $leave->employee_id,
                'leave_type_id' => $leaveType->getKey(),
                'year' => $leave->start_date->year,
            ],
            [
                'entitled_days' => $leaveType->default_entitlement_days,
                'updated_by' => $actor->getKey(),
            ],
        );

        $entitlement = LeaveEntitlement::query()
            ->lockForUpdate()
            ->findOrFail($entitlement->getKey());
        $balance = (float) $entitlement->entitled_days
            + (float) $entitlement->carry_forward_days
            + (float) $entitlement->adjustment_days
            + (float) $entitlement->transactions()->sum('days');

        if ($balance < (float) $leave->requested_days) {
            throw ValidationException::withMessages([
                'status' => sprintf(
                    'Baki %s tidak mencukupi. Baki semasa ialah %.1f hari.',
                    $leaveType->name,
                    $balance,
                ),
            ]);
        }

        LeaveBalanceTransaction::query()->firstOrCreate(
            [
                'leave_request_id' => $leave->getKey(),
                'transaction_type' => 'approval_deduction',
            ],
            [
                'leave_entitlement_id' => $entitlement->getKey(),
                'days' => -1 * (float) $leave->requested_days,
                'notes' => 'Potongan automatik selepas kelulusan akhir.',
                'performed_by' => $actor->getKey(),
            ],
        );
    }

    public function refund(EmployeeLeaveRequest $leave, User $actor): void
    {
        $deduction = LeaveBalanceTransaction::query()
            ->where('leave_request_id', $leave->getKey())
            ->where('transaction_type', 'approval_deduction')
            ->first();

        if (! $deduction) {
            return;
        }

        LeaveBalanceTransaction::query()->firstOrCreate(
            [
                'leave_request_id' => $leave->getKey(),
                'transaction_type' => 'cancellation_refund',
            ],
            [
                'leave_entitlement_id' => $deduction->leave_entitlement_id,
                'days' => abs((float) $deduction->days),
                'notes' => 'Pemulangan automatik selepas cuti diluluskan dibatalkan.',
                'performed_by' => $actor->getKey(),
            ],
        );
    }
}
