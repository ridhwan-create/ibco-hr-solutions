<?php

use App\Models\OvertimeApprovalAssignment;
use App\Models\OvertimeRequest;
use App\Models\OvertimeType;
use App\Models\User;
use App\Support\LeaveApprovalAlerts;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createPendingOvertimeAlert(
    User $employee,
    OvertimeType $type,
    int $departmentId,
    string $stage,
): OvertimeRequest {
    return OvertimeRequest::query()->create([
        'user_id' => $employee->getKey(),
        'employee_id' => 2000 + $departmentId,
        'department_id' => $departmentId,
        'overtime_type_id' => $type->getKey(),
        'work_date' => '2026-07-29',
        'start_at' => '2026-07-29 18:00:00',
        'end_at' => '2026-07-29 20:00:00',
        'break_minutes' => 0,
        'requested_minutes' => 120,
        'attendance_match_status' => 'matched',
        'reason' => 'Permohonan untuk menguji badge OT.',
        'work_description' => 'Menguji kiraan permohonan mengikut role.',
        'status' => 'pending',
        'approval_stage' => $stage,
        'submitted_at' => now(),
    ]);
}

test('approval centre separates overtime counts by actionable role', function () {
    $employee = User::factory()->employee()->create();
    $assignedSupervisor = User::factory()->supervisor()->create();
    $otherSupervisor = User::factory()->supervisor()->create();
    $hrAdmin = User::factory()->hrAdmin()->create();
    $hrManager = User::factory()->hrManager()->create();
    $superAdmin = User::factory()->superAdmin()->create();
    $type = OvertimeType::query()->where('code', 'WEEKDAY')->firstOrFail();

    OvertimeApprovalAssignment::query()->create([
        'department_id' => 10,
        'approver_user_id' => $assignedSupervisor->getKey(),
        'is_active' => true,
    ]);
    OvertimeApprovalAssignment::query()->create([
        'department_id' => 20,
        'approver_user_id' => $otherSupervisor->getKey(),
        'is_active' => true,
    ]);
    createPendingOvertimeAlert($employee, $type, 10, 'supervisor');
    createPendingOvertimeAlert($employee, $type, 20, 'supervisor');
    createPendingOvertimeAlert($employee, $type, 10, 'hr');

    $alerts = app(LeaveApprovalAlerts::class);

    expect($alerts->summarizeFor($assignedSupervisor))->toMatchArray([
        'enabled' => true,
        'total' => 1,
        'overtime_total' => 1,
        'overtime_supervisor' => 1,
        'overtime_hr' => 0,
    ]);
    expect($alerts->summarizeFor($hrAdmin))->toMatchArray([
        'enabled' => true,
        'total' => 0,
        'overtime_total' => 0,
        'overtime_supervisor' => 0,
        'overtime_hr' => 0,
    ]);
    expect($alerts->summarizeFor($hrManager))->toMatchArray([
        'enabled' => true,
        'total' => 1,
        'overtime_total' => 1,
        'overtime_supervisor' => 0,
        'overtime_hr' => 1,
    ]);
    expect($alerts->summarizeFor($superAdmin))->toMatchArray([
        'enabled' => true,
        'total' => 0,
        'overtime_total' => 0,
        'overtime_supervisor' => 0,
        'overtime_hr' => 0,
    ]);
    expect($alerts->summarizeFor($employee))->toMatchArray([
        'enabled' => false,
        'total' => 0,
        'overtime_total' => 0,
    ]);
});
