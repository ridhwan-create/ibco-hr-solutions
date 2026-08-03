<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('super admin can open user management', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->get(route('users.index'))
        ->assertOk();
});

test('non-super-admin roles cannot open user management', function (UserRole $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->get(route('users.index'))
        ->assertForbidden();
})->with([
    'Pengurus HR' => UserRole::HrManager,
    'HR Admin' => UserRole::HrAdmin,
    'Penyelia / Ketua Jabatan' => UserRole::Supervisor,
    'Viewer / Pemerhati' => UserRole::Viewer,
    'Employee' => UserRole::Employee,
]);

test('super admin can update another user role', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $user = User::factory()->create();

    $this->actingAs($superAdmin)
        ->patch(route('users.role.update', $user), [
            'roles' => [UserRole::HrAdmin->value],
        ])
        ->assertRedirect();

    expect($user->fresh()->resolvedRole())->toBe(UserRole::HrAdmin);
});

test('super admin cannot remove super admin role from their own account', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->from(route('users.index'))
        ->patch(route('users.role.update', $superAdmin), [
            'roles' => [UserRole::Viewer->value],
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHasErrors('roles');

    expect($superAdmin->fresh()->resolvedRole())->toBe(UserRole::SuperAdmin);
});

test('viewer cannot access payroll directly', function () {
    $viewer = User::factory()->create();

    $this->actingAs($viewer)
        ->get(route('payroll.index'))
        ->assertForbidden();
});

test('hr admin cannot manage user roles directly', function () {
    $hrAdmin = User::factory()->hrAdmin()->create();
    $user = User::factory()->create();

    $this->actingAs($hrAdmin)
        ->patch(route('users.role.update', $user), [
            'roles' => [UserRole::SuperAdmin->value],
        ])
        ->assertForbidden();

    expect($user->fresh()->resolvedRole())->toBe(UserRole::Viewer);
});

test('permissions are combined when a user has multiple roles', function () {
    $user = User::factory()->create();
    $user->syncRoles([
        UserRole::HrAdmin,
        UserRole::Employee,
    ]);

    expect($user->hasRole(UserRole::HrAdmin))->toBeTrue();
    expect($user->hasRole(UserRole::Employee))->toBeTrue();
    expect($user->hasPermission('payroll.view'))->toBeTrue();
    expect($user->hasPermission('attendance.clock'))->toBeTrue();
});

test('supervisor receives department leave review permission and can combine employee role', function () {
    $supervisor = User::factory()->supervisor()->create();

    expect($supervisor->hasPermission('leave.supervise'))->toBeTrue();
    expect($supervisor->hasPermission('overtime.supervise'))->toBeTrue();
    expect($supervisor->hasPermission('roster.supervise'))->toBeTrue();
    expect($supervisor->hasPermission('claims.supervise'))->toBeTrue();
    expect($supervisor->hasPermission('performance.supervise'))->toBeTrue();
    expect($supervisor->hasPermission('training.supervise'))->toBeTrue();
    expect($supervisor->hasPermission('competency.assess'))->toBeTrue();
    expect($supervisor->hasPermission('leave.manage'))->toBeFalse();
    expect($supervisor->hasPermission('overtime.manage'))->toBeFalse();
    expect($supervisor->hasPermission('roster.manage'))->toBeFalse();
    expect($supervisor->hasPermission('claims.manage'))->toBeFalse();
    expect($supervisor->hasPermission('performance.manage'))->toBeFalse();
    expect($supervisor->hasPermission('training.manage'))->toBeFalse();

    $supervisor->syncRoles([
        UserRole::Supervisor,
        UserRole::Employee,
    ]);

    expect($supervisor->hasPermission('leave.apply'))->toBeTrue();
    expect($supervisor->hasPermission('overtime.apply'))->toBeTrue();
    expect($supervisor->hasPermission('roster.swap'))->toBeTrue();
    expect($supervisor->hasPermission('claims.apply'))->toBeTrue();
    expect($supervisor->hasPermission('performance.self'))->toBeTrue();
    expect($supervisor->hasPermission('attendance.clock'))->toBeTrue();
    expect($supervisor->hasPermission('payslip.self'))->toBeTrue();
    expect($supervisor->hasPermission('training.apply'))->toBeTrue();
    expect($supervisor->hasPermission('competency.self'))->toBeTrue();
});

test('payroll permissions separate hr preparation from final approval', function () {
    $hrAdmin = User::factory()->hrAdmin()->create();
    $hrManager = User::factory()->hrManager()->create();
    $superAdmin = User::factory()->superAdmin()->create();

    expect($hrAdmin->hasPermission('payroll.view'))->toBeTrue();
    expect($hrAdmin->hasPermission('payroll.manage'))->toBeTrue();
    expect($hrAdmin->hasPermission('payroll.settings'))->toBeTrue();
    expect($hrAdmin->hasPermission('payroll.approve'))->toBeFalse();

    expect($hrManager->hasPermission('payroll.manage'))->toBeFalse();
    expect($hrManager->hasPermission('payroll.approve'))->toBeTrue();
    expect($superAdmin->hasPermission('payroll.manage'))->toBeTrue();
    expect($superAdmin->hasPermission('payroll.approve'))->toBeFalse();
    expect($superAdmin->hasPermission('payroll.settings'))->toBeTrue();
});

test('performance permissions separate employee supervisor and hr workflows', function () {
    $employee = User::factory()->employee()->create();
    $supervisor = User::factory()->supervisor()->create();
    $hr = User::factory()->hrAdmin()->create();
    $hrManager = User::factory()->hrManager()->create();
    $superAdmin = User::factory()->superAdmin()->create();

    expect($employee->hasPermission('performance.self'))->toBeTrue();
    expect($employee->hasPermission('performance.view'))->toBeFalse();
    expect($supervisor->hasPermission('performance.view'))->toBeTrue();
    expect($supervisor->hasPermission('performance.supervise'))->toBeTrue();
    expect($supervisor->hasPermission('performance.moderate'))->toBeFalse();
    expect($hr->hasPermission('performance.manage'))->toBeTrue();
    expect($hr->hasPermission('performance.moderate'))->toBeTrue();
    expect($hr->hasPermission('performance.finalize'))->toBeFalse();
    expect($hrManager->hasPermission('performance.moderate'))->toBeFalse();
    expect($hrManager->hasPermission('performance.finalize'))->toBeTrue();
    expect($superAdmin->hasPermission('performance.settings'))->toBeTrue();
    expect($superAdmin->hasPermission('performance.finalize'))->toBeFalse();
});

test('recruitment permissions separate employee panel hr and approval workflows', function () {
    $employee = User::factory()->employee()->create();
    $supervisor = User::factory()->supervisor()->create();
    $hr = User::factory()->hrAdmin()->create();
    $hrManager = User::factory()->hrManager()->create();
    $viewer = User::factory()->create();

    expect($employee->hasPermission('onboarding.self'))->toBeTrue();
    expect($employee->hasPermission('recruitment.view'))->toBeFalse();
    expect($supervisor->hasPermission('recruitment.view'))->toBeTrue();
    expect($supervisor->hasPermission('recruitment.interview'))->toBeTrue();
    expect($supervisor->hasPermission('recruitment.manage'))->toBeFalse();
    expect($hr->hasPermission('recruitment.manage'))->toBeTrue();
    expect($hr->hasPermission('recruitment.approve'))->toBeFalse();
    expect($hr->hasPermission('recruitment.settings'))->toBeTrue();
    expect($hr->hasPermission('onboarding.manage'))->toBeTrue();
    expect($hrManager->hasPermission('recruitment.approve'))->toBeTrue();
    expect($hrManager->hasPermission('onboarding.approve'))->toBeTrue();
    expect($viewer->hasPermission('recruitment.view'))->toBeTrue();
    expect($viewer->hasPermission('recruitment.interview'))->toBeFalse();
});

test('training permissions separate employee supervisor hr and viewer workflows', function () {
    $employee = User::factory()->employee()->create();
    $supervisor = User::factory()->supervisor()->create();
    $hr = User::factory()->hrAdmin()->create();
    $hrManager = User::factory()->hrManager()->create();
    $viewer = User::factory()->create();

    expect($employee->hasPermission('training.self'))->toBeTrue();
    expect($employee->hasPermission('training.apply'))->toBeTrue();
    expect($employee->hasPermission('competency.self'))->toBeTrue();
    expect($employee->hasPermission('training.view'))->toBeFalse();
    expect($supervisor->hasPermission('training.view'))->toBeTrue();
    expect($supervisor->hasPermission('training.supervise'))->toBeTrue();
    expect($supervisor->hasPermission('competency.assess'))->toBeTrue();
    expect($supervisor->hasPermission('training.manage'))->toBeFalse();
    expect($hr->hasPermission('training.manage'))->toBeTrue();
    expect($hr->hasPermission('training.settings'))->toBeTrue();
    expect($hr->hasPermission('competency.assess'))->toBeTrue();
    expect($hr->hasPermission('training.approve'))->toBeFalse();
    expect($hrManager->hasPermission('training.approve'))->toBeTrue();
    expect($viewer->hasPermission('training.view'))->toBeTrue();
    expect($viewer->hasPermission('training.manage'))->toBeFalse();
});

test('document permissions separate employee hr and approval workflows', function () {
    $employee = User::factory()->employee()->create();
    $supervisor = User::factory()->supervisor()->create();
    $hr = User::factory()->hrAdmin()->create();
    $hrManager = User::factory()->hrManager()->create();
    $superAdmin = User::factory()->superAdmin()->create();
    $viewer = User::factory()->create();

    expect($employee->hasPermission('documents.self'))->toBeTrue();
    expect($employee->hasPermission('documents.view'))->toBeFalse();
    expect($supervisor->hasPermission('documents.view'))->toBeTrue();
    expect($supervisor->hasPermission('documents.approve'))->toBeFalse();
    expect($supervisor->hasPermission('documents.manage'))->toBeFalse();
    expect($hr->hasPermission('documents.view'))->toBeTrue();
    expect($hr->hasPermission('documents.manage'))->toBeTrue();
    expect($hr->hasPermission('documents.settings'))->toBeTrue();
    expect($hr->hasPermission('documents.approve'))->toBeFalse();
    expect($hrManager->hasPermission('documents.approve'))->toBeTrue();
    expect($superAdmin->hasPermission('documents.approve'))->toBeFalse();
    expect($viewer->hasPermission('documents.view'))->toBeFalse();
});

test('discipline permissions separate complainant investigator hr and decision workflows', function () {
    $employee = User::factory()->employee()->create();
    $supervisor = User::factory()->supervisor()->create();
    $hr = User::factory()->hrAdmin()->create();
    $hrManager = User::factory()->hrManager()->create();
    $superAdmin = User::factory()->superAdmin()->create();
    $viewer = User::factory()->create();

    expect($employee->hasPermission('discipline.self'))->toBeTrue();
    expect($employee->hasPermission('discipline.apply'))->toBeTrue();
    expect($employee->hasPermission('discipline.view'))->toBeFalse();
    expect($supervisor->hasPermission('discipline.view'))->toBeTrue();
    expect($supervisor->hasPermission('discipline.investigate'))->toBeTrue();
    expect($supervisor->hasPermission('discipline.manage'))->toBeFalse();
    expect($hr->hasPermission('discipline.manage'))->toBeTrue();
    expect($hr->hasPermission('discipline.settings'))->toBeTrue();
    expect($hr->hasPermission('discipline.approve'))->toBeFalse();
    expect($hrManager->hasPermission('discipline.approve'))->toBeTrue();
    expect($superAdmin->hasPermission('discipline.approve'))->toBeFalse();
    expect($viewer->hasPermission('discipline.view'))->toBeFalse();
});

test('separation permissions separate employee supervisor clearance hr and approval workflows', function () {
    $employee = User::factory()->employee()->create();
    $supervisor = User::factory()->supervisor()->create();
    $hr = User::factory()->hrAdmin()->create();
    $hrManager = User::factory()->hrManager()->create();
    $superAdmin = User::factory()->superAdmin()->create();
    $viewer = User::factory()->create();

    expect($employee->hasPermission('separation.self'))->toBeTrue();
    expect($employee->hasPermission('separation.apply'))->toBeTrue();
    expect($employee->hasPermission('separation.view'))->toBeFalse();
    expect($supervisor->hasPermission('separation.view'))->toBeTrue();
    expect($supervisor->hasPermission('separation.supervise'))->toBeTrue();
    expect($supervisor->hasPermission('separation.clearance'))->toBeTrue();
    expect($supervisor->hasPermission('separation.manage'))->toBeFalse();
    expect($hr->hasPermission('separation.manage'))->toBeTrue();
    expect($hr->hasPermission('separation.approve'))->toBeFalse();
    expect($hr->hasPermission('separation.settings'))->toBeTrue();
    expect($hrManager->hasPermission('separation.approve'))->toBeTrue();
    expect($superAdmin->hasPermission('separation.approve'))->toBeFalse();
    expect($viewer->hasPermission('separation.clearance'))->toBeTrue();
    expect($viewer->hasPermission('separation.manage'))->toBeFalse();
});

test('hr manager owns final business approvals while technical and processing roles do not', function () {
    $hrManager = User::factory()->hrManager()->create();
    $hrAdmin = User::factory()->hrAdmin()->create();
    $superAdmin = User::factory()->superAdmin()->create();

    foreach ([
        'leave.approve',
        'overtime.approve',
        'claims.approve',
        'onboarding.approve',
        'training.approve',
        'roster.publish',
    ] as $permission) {
        expect($hrManager->hasPermission($permission))->toBeTrue();
        expect($hrAdmin->hasPermission($permission))->toBeFalse();
        expect($superAdmin->hasPermission($permission))->toBeFalse();
    }

    expect($superAdmin->hasPermission('users.manage'))->toBeTrue();
    expect($superAdmin->hasPermission('recruitment.manage'))->toBeTrue();
    expect($hrManager->hasPermission('users.manage'))->toBeFalse();
    expect($hrManager->hasPermission('recruitment.manage'))->toBeFalse();
});

test('hr manager cannot be combined with technical processing or supervisor roles', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $user = User::factory()->hrManager()->create();

    $this->actingAs($superAdmin)
        ->from(route('users.index'))
        ->patch(route('users.role.update', $user), [
            'roles' => [
                UserRole::HrManager->value,
                UserRole::HrAdmin->value,
            ],
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHasErrors('roles');

    expect($user->fresh()->hasRole(UserRole::HrManager))->toBeTrue();
    expect($user->fresh()->hasRole(UserRole::HrAdmin))->toBeFalse();
});
