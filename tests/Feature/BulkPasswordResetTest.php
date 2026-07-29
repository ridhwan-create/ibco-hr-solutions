<?php

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\EmployeeUserLink;
use App\Models\OfficeLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function createPasswordResetOffice(): OfficeLocation
{
    return OfficeLocation::query()->create([
        'name' => 'IBCO Solutions HQ',
        'address' => 'Kuala Lumpur',
        'latitude' => 3.1390000,
        'longitude' => 101.6869000,
        'radius_meters' => 100,
        'accuracy_limit_meters' => 100,
        'is_active' => true,
    ]);
}

function linkPasswordResetEmployee(
    User $user,
    OfficeLocation $office,
    int $employeeId,
): EmployeeUserLink {
    return EmployeeUserLink::query()->create([
        'user_id' => $user->getKey(),
        'employee_id' => $employeeId,
        'office_location_id' => $office->getKey(),
        'is_active' => true,
    ]);
}

test('super admin can view resettable employee users', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $office = createPasswordResetOffice();
    $employee = User::factory()->employee()->create([
        'name' => 'Employee Layak',
        'email' => 'layak@ibco.test',
    ]);
    linkPasswordResetEmployee($employee, $office, 1001);
    User::factory()->employee()->create([
        'name' => 'Employee Tanpa Pautan',
        'email' => 'tanpa.pautan@ibco.test',
    ]);
    User::factory()->create([
        'name' => 'Viewer Biasa',
        'email' => 'viewer@ibco.test',
    ]);

    $this->actingAs($superAdmin)
        ->get(route('users.password-reset.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('users/reset-passwords')
            ->has('users', 1)
            ->where('users.0.id', $employee->getKey())
            ->where('users.0.email', 'layak@ibco.test')
            ->where('users.0.roles.0', UserRole::Employee->value)
            ->where('users.0.office', 'IBCO Solutions HQ')
            ->where('resetResult', null));
});

test('bulk reset changes selected passwords without changing roles or employee links', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $office = createPasswordResetOffice();
    $first = User::factory()->employee()->create([
        'name' => 'Reset Pertama',
        'email' => 'reset.pertama@ibco.test',
        'password' => 'OldPasswordOne123!',
    ]);
    $first->syncRoles([
        UserRole::HrAdmin,
        UserRole::Employee,
    ]);
    $firstLink = linkPasswordResetEmployee($first, $office, 2001);
    $second = User::factory()->employee()->create([
        'name' => 'Reset Kedua',
        'email' => 'reset.kedua@ibco.test',
        'password' => 'OldPasswordTwo123!',
    ]);
    $secondLink = linkPasswordResetEmployee($second, $office, 2002);
    $notSelected = User::factory()->employee()->create([
        'name' => 'Tidak Dipilih',
        'email' => 'tidak.dipilih@ibco.test',
        'password' => 'UnchangedPassword123!',
    ]);
    linkPasswordResetEmployee($notSelected, $office, 2003);
    $firstRoles = $first->roleValues();
    $secondRoles = $second->roleValues();
    $notSelectedPassword = $notSelected->password;

    $this->actingAs($superAdmin)
        ->post(route('users.password-reset.store'), [
            'user_ids' => [$first->getKey(), $second->getKey()],
        ])
        ->assertRedirect(route('users.password-reset.create'))
        ->assertSessionDoesntHaveErrors();

    $result = session('bulk_password_reset_result');

    expect($result['reset_count'])->toBe(2);
    expect($result['credentials'])->toHaveCount(2);

    foreach ($result['credentials'] as $credential) {
        $user = User::query()->findOrFail($credential['user_id']);

        expect(Hash::check(
            $credential['temporary_password'],
            $user->password,
        ))->toBeTrue();
        expect($credential['temporary_password'])->toHaveLength(16);
    }

    $first->refresh();
    $second->refresh();
    $notSelected->refresh();

    expect($first->roleValues())->toBe($firstRoles);
    expect($second->roleValues())->toBe($secondRoles);
    expect($firstLink->fresh()->is_active)->toBeTrue();
    expect($secondLink->fresh()->is_active)->toBeTrue();
    expect($notSelected->password)->toBe($notSelectedPassword);
    expect(AuditLog::query()
        ->where('action', 'user.password.bulk_reset')
        ->count())->toBe(2);

    AuditLog::query()
        ->where('action', 'user.password.bulk_reset')
        ->get()
        ->each(function (AuditLog $log) {
            expect($log->old_values)->toBeNull();
            expect($log->new_values)->toBe([
                'method' => 'temporary_password',
                'source' => 'bulk_password_reset',
            ]);
            expect(array_keys($log->new_values))
                ->not->toContain('password', 'temporary_password');
        });
});

test('current user and accounts without an active employee link cannot be reset in bulk', function () {
    $superAdmin = User::factory()->superAdmin()->create([
        'password' => 'SuperAdminPassword123!',
    ]);
    $superAdmin->syncRoles([
        UserRole::SuperAdmin,
        UserRole::Employee,
    ]);
    $office = createPasswordResetOffice();
    linkPasswordResetEmployee($superAdmin, $office, 3001);
    $unlinkedEmployee = User::factory()->employee()->create([
        'password' => 'UnlinkedPassword123!',
    ]);
    $superAdminPassword = $superAdmin->password;
    $unlinkedPassword = $unlinkedEmployee->password;

    $this->actingAs($superAdmin)
        ->from(route('users.password-reset.create'))
        ->post(route('users.password-reset.store'), [
            'user_ids' => [
                $superAdmin->getKey(),
                $unlinkedEmployee->getKey(),
            ],
        ])
        ->assertRedirect(route('users.password-reset.create'))
        ->assertSessionHasErrors('user_ids');

    expect($superAdmin->fresh()->password)->toBe($superAdminPassword);
    expect($unlinkedEmployee->fresh()->password)->toBe($unlinkedPassword);
    expect(AuditLog::query()
        ->where('action', 'user.password.bulk_reset')
        ->count())->toBe(0);
});

test('non super admin cannot access bulk password reset', function () {
    $hrAdmin = User::factory()->hrAdmin()->create();
    $office = createPasswordResetOffice();
    $employee = User::factory()->employee()->create();
    linkPasswordResetEmployee($employee, $office, 4001);

    $this->actingAs($hrAdmin)
        ->get(route('users.password-reset.create'))
        ->assertForbidden();

    $this->actingAs($hrAdmin)
        ->post(route('users.password-reset.store'), [
            'user_ids' => [$employee->getKey()],
        ])
        ->assertForbidden();

    expect(AuditLog::query()
        ->where('action', 'user.password.bulk_reset')
        ->count())->toBe(0);
});
