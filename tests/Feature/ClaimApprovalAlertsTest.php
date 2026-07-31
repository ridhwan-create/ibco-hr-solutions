<?php

use App\Models\ClaimApprovalAssignment;
use App\Models\ClaimRequest;
use App\Models\ClaimType;
use App\Models\User;
use App\Support\LeaveApprovalAlerts;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createPendingClaimAlert(
    User $employee,
    ClaimType $type,
    int $departmentId,
    string $stage,
): ClaimRequest {
    return ClaimRequest::query()->create([
        'user_id' => $employee->getKey(),
        'employee_id' => 3000 + $departmentId,
        'department_id' => $departmentId,
        'claim_type_id' => $type->getKey(),
        'expense_date' => '2026-07-30',
        'requested_amount' => 100,
        'description' => 'Tuntutan untuk menguji badge kelulusan.',
        'status' => 'pending',
        'approval_stage' => $stage,
        'submitted_at' => now(),
    ]);
}

test('approval centre separates claim counts by actionable role', function () {
    $employee = User::factory()->employee()->create();
    $assignedSupervisor = User::factory()->supervisor()->create();
    $otherSupervisor = User::factory()->supervisor()->create();
    $hrAdmin = User::factory()->hrAdmin()->create();
    $superAdmin = User::factory()->superAdmin()->create();
    $type = ClaimType::query()->where('code', 'TRAVEL')->firstOrFail();

    ClaimApprovalAssignment::query()->create([
        'department_id' => 10,
        'approver_user_id' => $assignedSupervisor->getKey(),
        'is_active' => true,
    ]);
    ClaimApprovalAssignment::query()->create([
        'department_id' => 20,
        'approver_user_id' => $otherSupervisor->getKey(),
        'is_active' => true,
    ]);
    createPendingClaimAlert($employee, $type, 10, 'supervisor');
    createPendingClaimAlert($employee, $type, 20, 'supervisor');
    createPendingClaimAlert($employee, $type, 10, 'finance');

    $alerts = app(LeaveApprovalAlerts::class);

    expect($alerts->summarizeFor($assignedSupervisor))->toMatchArray([
        'enabled' => true,
        'total' => 1,
        'claim_total' => 1,
        'claim_supervisor' => 1,
        'claim_finance' => 0,
    ]);
    expect($alerts->summarizeFor($hrAdmin))->toMatchArray([
        'enabled' => true,
        'total' => 1,
        'claim_total' => 1,
        'claim_supervisor' => 0,
        'claim_finance' => 1,
    ]);
    expect($alerts->summarizeFor($superAdmin))->toMatchArray([
        'enabled' => true,
        'total' => 3,
        'claim_total' => 3,
        'claim_supervisor' => 2,
        'claim_finance' => 1,
    ]);
    expect($alerts->summarizeFor($employee))->toMatchArray([
        'enabled' => false,
        'total' => 0,
        'claim_total' => 0,
    ]);
});
