<?php

namespace App\Support;

use App\Models\EmployeeLeaveRequest;
use App\Models\ClaimApprovalAssignment;
use App\Models\ClaimRequest;
use App\Models\LeaveApprovalAssignment;
use App\Models\OvertimeApprovalAssignment;
use App\Models\OvertimeRequest;
use App\Models\PerformanceReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class LeaveApprovalAlerts
{
    /**
     * @return array{
     *     enabled: bool,
     *     total: int,
     *     supervisor: int,
     *     hr: int,
     *     polling_seconds: int,
     *     leave_total: int,
     *     leave_supervisor: int,
     *     leave_hr: int,
     *     overtime_total: int,
     *     overtime_supervisor: int,
     *     overtime_hr: int,
     *     claim_total: int,
     *     claim_supervisor: int,
     *     claim_finance: int,
     *     performance_total: int,
     *     performance_supervisor: int,
     *     performance_hr: int
     * }
     */
    public function summarizeFor(?User $user): array
    {
        $summary = [
            'enabled' => false,
            'total' => 0,
            'supervisor' => 0,
            'hr' => 0,
            'polling_seconds' => 60,
            'leave_total' => 0,
            'leave_supervisor' => 0,
            'leave_hr' => 0,
            'overtime_total' => 0,
            'overtime_supervisor' => 0,
            'overtime_hr' => 0,
            'claim_total' => 0,
            'claim_supervisor' => 0,
            'claim_finance' => 0,
            'performance_total' => 0,
            'performance_supervisor' => 0,
            'performance_hr' => 0,
        ];

        if (! $user) {
            return $summary;
        }

        $canManageLeave = $user->hasPermission('leave.manage');
        $canSuperviseLeave = $user->hasPermission('leave.supervise');
        $canManageOvertime = $user->hasPermission('overtime.manage');
        $canSuperviseOvertime = $user->hasPermission('overtime.supervise');
        $canManageClaims = $user->hasPermission('claims.manage');
        $canSuperviseClaims = $user->hasPermission('claims.supervise');
        $canSupervisePerformance = $user->hasPermission('performance.supervise');
        $canModeratePerformance = $user->hasPermission('performance.moderate');

        if (
            ! $canManageLeave
            && ! $canSuperviseLeave
            && ! $canManageOvertime
            && ! $canSuperviseOvertime
            && ! $canManageClaims
            && ! $canSuperviseClaims
            && ! $canSupervisePerformance
            && ! $canModeratePerformance
        ) {
            return $summary;
        }

        $leaveReady = Schema::hasTable('employee_leave_requests')
            && Schema::hasTable('leave_approval_assignments');
        $leaveSupervisor = $leaveReady && $canSuperviseLeave
            ? $this->leaveSupervisorQuery($user, $canManageLeave)->count()
            : 0;
        $leaveHr = $leaveReady && $canManageLeave
            ? EmployeeLeaveRequest::query()
                ->where('status', 'pending')
                ->where(function (Builder $query) {
                    $query->where('approval_stage', 'hr')
                        ->orWhereNull('approval_stage');
                })
                ->count()
            : 0;
        $overtimeReady = Schema::hasTable('overtime_requests')
            && Schema::hasTable('overtime_approval_assignments');
        $overtimeSupervisor = $overtimeReady && $canSuperviseOvertime
            ? $this->overtimeSupervisorQuery($user, $canManageOvertime)->count()
            : 0;
        $overtimeHr = $overtimeReady && $canManageOvertime
            ? OvertimeRequest::query()
                ->where('status', 'pending')
                ->where(function (Builder $query) {
                    $query->where('approval_stage', 'hr')
                        ->orWhereNull('approval_stage');
                })
                ->count()
            : 0;
        $claimsReady = Schema::hasTable('claim_requests')
            && Schema::hasTable('claim_approval_assignments');
        $claimSupervisor = $claimsReady && $canSuperviseClaims
            ? $this->claimSupervisorQuery($user, $canManageClaims)->count()
            : 0;
        $claimFinance = $claimsReady && $canManageClaims
            ? ClaimRequest::query()
                ->where('status', 'pending')
                ->where(function (Builder $query) {
                    $query->where('approval_stage', 'finance')
                        ->orWhereNull('approval_stage');
                })
                ->count()
            : 0;
        $performanceReady = Schema::hasTable('performance_reviews');
        $performanceSupervisor = $performanceReady && $canSupervisePerformance
            ? PerformanceReview::query()
                ->where('status', 'supervisor_assessment')
                ->when(
                    ! $user->hasPermission('performance.manage'),
                    fn (Builder $query) => $query->where(
                        'supervisor_user_id',
                        $user->getAuthIdentifier(),
                    ),
                )
                ->count()
            : 0;
        $performanceHr = $performanceReady && $canModeratePerformance
            ? PerformanceReview::query()
                ->where('status', 'hr_moderation')
                ->count()
            : 0;
        $leaveTotal = $leaveSupervisor + $leaveHr;
        $overtimeTotal = $overtimeSupervisor + $overtimeHr;
        $claimTotal = $claimSupervisor + $claimFinance;
        $performanceTotal = $performanceSupervisor + $performanceHr;

        return [
            ...$summary,
            'enabled' => true,
            'total' => $leaveTotal + $overtimeTotal + $claimTotal + $performanceTotal,
            'supervisor' => $leaveSupervisor + $overtimeSupervisor + $claimSupervisor + $performanceSupervisor,
            'hr' => $leaveHr + $overtimeHr + $claimFinance + $performanceHr,
            'leave_total' => $leaveTotal,
            'leave_supervisor' => $leaveSupervisor,
            'leave_hr' => $leaveHr,
            'overtime_total' => $overtimeTotal,
            'overtime_supervisor' => $overtimeSupervisor,
            'overtime_hr' => $overtimeHr,
            'claim_total' => $claimTotal,
            'claim_supervisor' => $claimSupervisor,
            'claim_finance' => $claimFinance,
            'performance_total' => $performanceTotal,
            'performance_supervisor' => $performanceSupervisor,
            'performance_hr' => $performanceHr,
        ];
    }

    private function leaveSupervisorQuery(User $user, bool $canManage): Builder
    {
        $query = EmployeeLeaveRequest::query()
            ->where('status', 'pending')
            ->where('approval_stage', 'supervisor');

        if ($canManage) {
            return $query;
        }

        $departmentIds = LeaveApprovalAssignment::query()
            ->where('approver_user_id', $user->getAuthIdentifier())
            ->where('is_active', true)
            ->pluck('department_id');

        return $query->whereIn('department_id', $departmentIds);
    }

    private function overtimeSupervisorQuery(User $user, bool $canManage): Builder
    {
        $query = OvertimeRequest::query()
            ->where('status', 'pending')
            ->where('approval_stage', 'supervisor');

        if ($canManage) {
            return $query;
        }

        $departmentIds = OvertimeApprovalAssignment::query()
            ->where('approver_user_id', $user->getAuthIdentifier())
            ->where('is_active', true)
            ->pluck('department_id');

        return $query->whereIn('department_id', $departmentIds);
    }

    private function claimSupervisorQuery(User $user, bool $canManage): Builder
    {
        $query = ClaimRequest::query()
            ->where('status', 'pending')
            ->where('approval_stage', 'supervisor');

        if ($canManage) {
            return $query;
        }

        $departmentIds = ClaimApprovalAssignment::query()
            ->where('approver_user_id', $user->getAuthIdentifier())
            ->where('is_active', true)
            ->pluck('department_id');

        return $query->whereIn('department_id', $departmentIds);
    }
}
