<?php

use App\Models\EmployeeLeaveRequest;
use App\Models\LeaveApprovalAssignment;
use App\Models\LeaveType;
use App\Models\User;
use App\Support\LeaveApprovalAlerts;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createPendingLeaveAlert(
    User $employee,
    LeaveType $leaveType,
    int $departmentId,
    string $stage,
): EmployeeLeaveRequest {
    return EmployeeLeaveRequest::query()->create([
        'user_id' => $employee->getKey(),
        'employee_id' => 1000 + $departmentId,
        'department_id' => $departmentId,
        'leave_type_id' => $leaveType->getKey(),
        'system_leave_type_id' => $leaveType->getKey(),
        'leave_type_label' => $leaveType->name,
        'start_date' => '2026-08-03',
        'end_date' => '2026-08-03',
        'duration_type' => 'full_day',
        'requested_days' => 1,
        'reason' => 'Permohonan untuk menguji badge notifikasi.',
        'status' => 'pending',
        'approval_stage' => $stage,
        'submitted_at' => now(),
    ]);
}

test('leave approval badges only count requests actionable by each role', function () {
    $employee = User::factory()->employee()->create();
    $assignedSupervisor = User::factory()->supervisor()->create();
    $otherSupervisor = User::factory()->supervisor()->create();
    $hrAdmin = User::factory()->hrAdmin()->create();
    $hrManager = User::factory()->hrManager()->create();
    $superAdmin = User::factory()->superAdmin()->create();
    $leaveType = LeaveType::query()->where('code', 'ANNUAL')->firstOrFail();

    LeaveApprovalAssignment::query()->create([
        'department_id' => 10,
        'approver_user_id' => $assignedSupervisor->getKey(),
        'is_active' => true,
    ]);
    LeaveApprovalAssignment::query()->create([
        'department_id' => 20,
        'approver_user_id' => $otherSupervisor->getKey(),
        'is_active' => true,
    ]);

    createPendingLeaveAlert($employee, $leaveType, 10, 'supervisor');
    createPendingLeaveAlert($employee, $leaveType, 20, 'supervisor');
    createPendingLeaveAlert($employee, $leaveType, 10, 'hr');
    $completed = createPendingLeaveAlert(
        $employee,
        $leaveType,
        10,
        'completed',
    );
    $completed->update(['status' => 'approved']);

    $alerts = app(LeaveApprovalAlerts::class);

    expect($alerts->summarizeFor($assignedSupervisor))->toMatchArray([
        'enabled' => true,
        'total' => 1,
        'supervisor' => 1,
        'hr' => 0,
    ]);
    expect($alerts->summarizeFor($hrAdmin))->toMatchArray([
        'enabled' => true,
        'total' => 0,
        'supervisor' => 0,
        'hr' => 0,
    ]);
    expect($alerts->summarizeFor($hrManager))->toMatchArray([
        'enabled' => true,
        'total' => 1,
        'supervisor' => 0,
        'hr' => 1,
    ]);
    expect($alerts->summarizeFor($superAdmin))->toMatchArray([
        'enabled' => true,
        'total' => 0,
        'supervisor' => 0,
        'hr' => 0,
    ]);
    expect($alerts->summarizeFor($employee))->toMatchArray([
        'enabled' => false,
        'total' => 0,
        'supervisor' => 0,
        'hr' => 0,
    ]);
});
