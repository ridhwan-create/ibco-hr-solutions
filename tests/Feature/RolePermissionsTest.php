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
    'HR Admin' => UserRole::HrAdmin,
    'Penyelia / Ketua Jabatan' => UserRole::Supervisor,
    'Viewer / Manager' => UserRole::Viewer,
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
    expect($supervisor->hasPermission('leave.manage'))->toBeFalse();

    $supervisor->syncRoles([
        UserRole::Supervisor,
        UserRole::Employee,
    ]);

    expect($supervisor->hasPermission('leave.apply'))->toBeTrue();
    expect($supervisor->hasPermission('attendance.clock'))->toBeTrue();
});
