<?php

use App\Enums\UserRole;
use App\Models\EmployeeUserLink;
use App\Models\OfficeLocation;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('database.connections.ibco', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);

    DB::purge('ibco');

    Schema::connection('ibco')->create('maklumatpekerja', function (Blueprint $table) {
        $table->increments('id');
        $table->string('employeeID', 15)->nullable();
        $table->string('nama')->nullable();
        $table->string('email')->nullable();
        $table->boolean('rcd_enable')->default(true);
    });
});

afterEach(function () {
    DB::disconnect('ibco');
});

function createManagedEmployee(array $overrides = []): int
{
    return DB::connection('ibco')->table('maklumatpekerja')->insertGetId([
        'employeeID' => 'EMP-USER-001',
        'nama' => 'Pekerja Sistem',
        'email' => 'pekerja@ibco.test',
        'rcd_enable' => 1,
        ...$overrides,
    ]);
}

function createManagedOffice(): OfficeLocation
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

test('super admin can open the add user form with employee options', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $employeeId = createManagedEmployee();
    createManagedOffice();

    $this->actingAs($superAdmin)
        ->get(route('users.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('users/create')
            ->where('defaultRoles', [])
            ->where('options.employees.0.id', $employeeId)
            ->where('options.offices.0.radius_meters', 100));
});

test('super admin can register and link an employee without writing to db_spp', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $employeeId = createManagedEmployee();
    $office = createManagedOffice();
    $legacyQueries = [];

    DB::listen(function (QueryExecuted $event) use (&$legacyQueries) {
        if ($event->connectionName === 'ibco') {
            $legacyQueries[] = strtolower(trim($event->sql));
        }
    });

    $this->actingAs($superAdmin)
        ->post(route('users.store'), [
            'name' => 'Pekerja Sistem',
            'email' => 'pengguna@ibco.test',
            'roles' => [UserRole::Employee->value],
            'employee_id' => $employeeId,
            'office_location_id' => $office->getKey(),
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionDoesntHaveErrors();

    $user = User::query()->where('email', 'pengguna@ibco.test')->sole();

    expect($user->resolvedRole())->toBe(UserRole::Employee);
    expect($user->email_verified_at)->not->toBeNull();
    expect(Hash::check('Password123!', $user->password))->toBeTrue();
    expect($legacyQueries)->not->toBeEmpty();
    expect($legacyQueries)->each->toStartWith('select');

    $this->assertDatabaseHas('employee_user_links', [
        'user_id' => $user->getKey(),
        'employee_id' => $employeeId,
        'office_location_id' => $office->getKey(),
        'is_active' => 1,
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'user.created',
        'auditable_type' => 'users',
        'auditable_id' => (string) $user->getKey(),
    ]);
});

test('super admin can also hold employee role and record attendance', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $employeeId = createManagedEmployee();
    $office = createManagedOffice();

    $this->actingAs($superAdmin)
        ->put(route('users.update', $superAdmin), [
            'name' => $superAdmin->name,
            'email' => $superAdmin->email,
            'roles' => [
                UserRole::SuperAdmin->value,
                UserRole::Employee->value,
            ],
            'employee_id' => $employeeId,
            'office_location_id' => $office->getKey(),
            'password' => '',
            'password_confirmation' => '',
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionDoesntHaveErrors();

    $superAdmin->refresh();

    expect($superAdmin->hasRole(UserRole::SuperAdmin))->toBeTrue();
    expect($superAdmin->hasRole(UserRole::Employee))->toBeTrue();
    expect($superAdmin->hasPermission('users.manage'))->toBeTrue();
    expect($superAdmin->hasPermission('attendance.clock'))->toBeTrue();
    $this->assertDatabaseHas('employee_user_links', [
        'user_id' => $superAdmin->getKey(),
        'employee_id' => $employeeId,
        'office_location_id' => $office->getKey(),
        'is_active' => 1,
    ]);
});

test('the same employee cannot be linked to two active system accounts', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $existingUser = User::factory()->employee()->create();
    $employeeId = createManagedEmployee();
    $office = createManagedOffice();

    EmployeeUserLink::query()->create([
        'user_id' => $existingUser->getKey(),
        'employee_id' => $employeeId,
        'office_location_id' => $office->getKey(),
        'is_active' => true,
    ]);

    $this->actingAs($superAdmin)
        ->from(route('users.create'))
        ->post(route('users.store'), [
            'name' => 'Akaun Pendua',
            'email' => 'pendua@ibco.test',
            'roles' => [UserRole::Employee->value],
            'employee_id' => $employeeId,
            'office_location_id' => $office->getKey(),
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])
        ->assertRedirect(route('users.create'))
        ->assertSessionHasErrors('employee_id');

    expect(User::query()->where('email', 'pendua@ibco.test')->exists())
        ->toBeFalse();
});

test('super admin can update user details and reset the password', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $managedUser = User::factory()->create([
        'name' => 'Nama Lama',
        'email' => 'lama@ibco.test',
        'role' => UserRole::Viewer,
    ]);

    $this->actingAs($superAdmin)
        ->put(route('users.update', $managedUser), [
            'name' => 'Nama Baharu',
            'email' => 'baharu@ibco.test',
            'roles' => [UserRole::HrAdmin->value],
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionDoesntHaveErrors();

    $managedUser->refresh();

    expect($managedUser->name)->toBe('Nama Baharu');
    expect($managedUser->email)->toBe('baharu@ibco.test');
    expect($managedUser->resolvedRole())->toBe(UserRole::HrAdmin);
    expect(Hash::check('NewPassword123!', $managedUser->password))->toBeTrue();
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'user.updated',
        'auditable_type' => 'users',
        'auditable_id' => (string) $managedUser->getKey(),
    ]);
});

test('changing employee to another role deactivates the attendance link', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $employeeUser = User::factory()->employee()->create();
    $employeeId = createManagedEmployee();
    $office = createManagedOffice();
    $link = EmployeeUserLink::query()->create([
        'user_id' => $employeeUser->getKey(),
        'employee_id' => $employeeId,
        'office_location_id' => $office->getKey(),
        'is_active' => true,
    ]);

    $this->actingAs($superAdmin)
        ->put(route('users.update', $employeeUser), [
            'name' => $employeeUser->name,
            'email' => $employeeUser->email,
            'roles' => [UserRole::Viewer->value],
            'password' => '',
            'password_confirmation' => '',
        ])
        ->assertRedirect(route('users.index'));

    expect($employeeUser->fresh()->resolvedRole())->toBe(UserRole::Viewer);
    expect($link->fresh()->is_active)->toBeFalse();
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'employee_link.deactivated',
        'auditable_id' => (string) $link->getKey(),
    ]);
});

test('non super admin cannot create or update system users', function () {
    $hrAdmin = User::factory()->hrAdmin()->create();
    $managedUser = User::factory()->create();

    $this->actingAs($hrAdmin)
        ->get(route('users.create'))
        ->assertForbidden();

    $this->actingAs($hrAdmin)
        ->post(route('users.store'), [
            'name' => 'Tidak Dibenarkan',
            'email' => 'blocked@ibco.test',
            'roles' => [UserRole::Viewer->value],
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])
        ->assertForbidden();

    $this->actingAs($hrAdmin)
        ->put(route('users.update', $managedUser), [
            'name' => 'Cubaan Edit',
            'email' => $managedUser->email,
            'roles' => [UserRole::Viewer->value],
            'password' => '',
            'password_confirmation' => '',
        ])
        ->assertForbidden();

    expect(User::query()->where('email', 'blocked@ibco.test')->exists())
        ->toBeFalse();
    expect($managedUser->fresh()->name)->not->toBe('Cubaan Edit');
});
