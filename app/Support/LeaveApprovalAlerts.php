<?php

namespace App\Support;

use App\Models\EmployeeLeaveRequest;
use App\Models\LeaveApprovalAssignment;
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
     *     polling_seconds: int
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
        ];

        if (
            ! $user
            || ! Schema::hasTable('employee_leave_requests')
            || ! Schema::hasTable('leave_approval_assignments')
        ) {
            return $summary;
        }

        $canManage = $user->hasPermission('leave.manage');
        $canSupervise = $user->hasPermission('leave.supervise');

        if (! $canManage && ! $canSupervise) {
            return $summary;
        }

        $supervisorCount = $canSupervise
            ? $this->supervisorQuery($user, $canManage)->count()
            : 0;
        $hrCount = $canManage
            ? EmployeeLeaveRequest::query()
                ->where('status', 'pending')
                ->where(function (Builder $query) {
                    $query->where('approval_stage', 'hr')
                        ->orWhereNull('approval_stage');
                })
                ->count()
            : 0;

        return [
            ...$summary,
            'enabled' => true,
            'total' => $supervisorCount + $hrCount,
            'supervisor' => $supervisorCount,
            'hr' => $hrCount,
        ];
    }

    private function supervisorQuery(User $user, bool $canManage): Builder
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
}
