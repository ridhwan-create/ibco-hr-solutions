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

function createImportEmployee(array $overrides = []): int
{
    static $sequence = 0;
    $sequence++;

    return DB::connection('ibco')->table('maklumatpekerja')->insertGetId([
        'employeeID' => sprintf('EMP-IMPORT-%03d', $sequence),
        'nama' => "Pekerja Import {$sequence}",
        'email' => "import{$sequence}@ibco.test",
        'rcd_enable' => 1,
        ...$overrides,
    ]);
}

function createImportOffice(): OfficeLocation
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

test('super admin can preview importable employees and data issues', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    createImportOffice();
    createImportEmployee([
        'nama' => 'Alpha Valid',
        'email' => 'alpha@ibco.test',
    ]);
    createImportEmployee([
        'nama' => 'Beta Tiada E-mel',
        'email' => null,
    ]);
    createImportEmployee([
        'nama' => 'Charlie Pendua',
        'email' => 'duplicate@ibco.test',
    ]);
    createImportEmployee([
        'nama' => 'Delta Pendua',
        'email' => 'DUPLICATE@ibco.test',
    ]);
    $existingEmployeeId = createImportEmployee([
        'nama' => 'Echo Akaun Sedia Ada',
        'email' => 'existing@ibco.test',
    ]);
    User::factory()->hrAdmin()->create([
        'email' => 'existing@ibco.test',
    ]);
    $linkedEmployeeId = createImportEmployee([
        'nama' => 'Foxtrot Sudah Daftar',
        'email' => 'linked@ibco.test',
    ]);
    $linkedUser = User::factory()->employee()->create([
        'email' => 'linked@ibco.test',
    ]);
    EmployeeUserLink::query()->create([
        'user_id' => $linkedUser->getKey(),
        'employee_id' => $linkedEmployeeId,
        'office_location_id' => OfficeLocation::query()->value('id'),
        'is_active' => true,
    ]);

    $this->actingAs($superAdmin)
        ->get(route('users.import.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('users/import-employees')
            ->has('employees', 5)
            ->where('employees.0.status', 'new_account')
            ->where('employees.0.can_import', true)
            ->where('employees.1.status', 'invalid_email')
            ->where('employees.1.can_import', false)
            ->where('employees.2.status', 'duplicate_email')
            ->where('employees.3.status', 'duplicate_email')
            ->where('employees.4.id', $existingEmployeeId)
            ->where('employees.4.status', 'existing_account')
            ->where('employees.4.existing_user.roles.0', UserRole::HrAdmin->value)
            ->where('statistics.active_employees', 6)
            ->where('statistics.already_registered', 1)
            ->where('statistics.ready_to_import', 2)
            ->where('statistics.requires_attention', 3));
});

test('bulk import creates employee users and only reads db_spp', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $office = createImportOffice();
    $firstEmployeeId = createImportEmployee([
        'nama' => 'Import Pertama',
        'email' => 'first.import@ibco.test',
    ]);
    $secondEmployeeId = createImportEmployee([
        'nama' => 'Import Kedua',
        'email' => 'second.import@ibco.test',
    ]);
    $legacyQueries = [];

    DB::listen(function (QueryExecuted $event) use (&$legacyQueries) {
        if ($event->connectionName === 'ibco') {
            $legacyQueries[] = strtolower(trim($event->sql));
        }
    });

    $response = $this->actingAs($superAdmin)
        ->post(route('users.import.store'), [
            'employee_ids' => [$firstEmployeeId, $secondEmployeeId],
            'office_location_id' => $office->getKey(),
        ])
        ->assertRedirect(route('users.import.create'))
        ->assertSessionDoesntHaveErrors();
    $result = session('employee_user_import_result');

    expect($result['created_count'])->toBe(2);
    expect($result['linked_count'])->toBe(0);
    expect($result['credentials'])->toHaveCount(2);
    expect($legacyQueries)->not->toBeEmpty();
    expect($legacyQueries)->each->toStartWith('select');

    foreach ($result['credentials'] as $credential) {
        $user = User::query()
            ->where('email', $credential['email'])
            ->sole();

        expect($user->hasOnlyRole(UserRole::Employee))->toBeTrue();
        expect($user->email_verified_at)->not->toBeNull();
        expect(Hash::check(
            $credential['temporary_password'],
            $user->password,
        ))->toBeTrue();
    }

    $this->assertDatabaseHas('employee_user_links', [
        'employee_id' => $firstEmployeeId,
        'office_location_id' => $office->getKey(),
        'is_active' => 1,
    ]);
    $this->assertDatabaseHas('employee_user_links', [
        'employee_id' => $secondEmployeeId,
        'office_location_id' => $office->getKey(),
        'is_active' => 1,
    ]);
    $this->assertDatabaseCount('users', 3);
    $this->assertDatabaseCount('employee_user_links', 2);
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'user.created',
        'auditable_type' => 'users',
    ]);
});

test('import adds employee role to an existing account without changing its password', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $existingUser = User::factory()->hrAdmin()->create([
        'name' => 'Pentadbir Sedia Ada',
        'email' => 'admin.employee@ibco.test',
        'password' => 'ExistingPassword123!',
    ]);
    $passwordHash = $existingUser->password;
    $office = createImportOffice();
    $employeeId = createImportEmployee([
        'nama' => 'Pentadbir Sedia Ada',
        'email' => 'ADMIN.EMPLOYEE@ibco.test',
    ]);

    $response = $this->actingAs($superAdmin)
        ->post(route('users.import.store'), [
            'employee_ids' => [$employeeId],
            'office_location_id' => $office->getKey(),
        ])
        ->assertRedirect(route('users.import.create'))
        ->assertSessionDoesntHaveErrors();
    $result = session('employee_user_import_result');

    $existingUser->refresh();

    expect($result['created_count'])->toBe(0);
    expect($result['linked_count'])->toBe(1);
    expect($result['credentials'])->toBe([]);
    expect($existingUser->password)->toBe($passwordHash);
    expect($existingUser->hasRole(UserRole::HrAdmin))->toBeTrue();
    expect($existingUser->hasRole(UserRole::Employee))->toBeTrue();
    $this->assertDatabaseHas('employee_user_links', [
        'user_id' => $existingUser->getKey(),
        'employee_id' => $employeeId,
        'office_location_id' => $office->getKey(),
        'is_active' => 1,
    ]);
});

test('invalid or duplicate employee email cannot be imported', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $office = createImportOffice();
    $invalidEmployeeId = createImportEmployee([
        'email' => 'bukan-email',
    ]);
    $duplicateEmployeeId = createImportEmployee([
        'email' => 'duplicate.import@ibco.test',
    ]);
    createImportEmployee([
        'email' => 'DUPLICATE.IMPORT@ibco.test',
    ]);

    $this->actingAs($superAdmin)
        ->from(route('users.import.create'))
        ->post(route('users.import.store'), [
            'employee_ids' => [$invalidEmployeeId],
            'office_location_id' => $office->getKey(),
        ])
        ->assertRedirect(route('users.import.create'))
        ->assertSessionHasErrors('employee_ids');

    $this->actingAs($superAdmin)
        ->from(route('users.import.create'))
        ->post(route('users.import.store'), [
            'employee_ids' => [$duplicateEmployeeId],
            'office_location_id' => $office->getKey(),
        ])
        ->assertRedirect(route('users.import.create'))
        ->assertSessionHasErrors('employee_ids');

    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('employee_user_links', 0);
});

test('reimporting a linked employee does not create a duplicate account', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $office = createImportOffice();
    $employeeId = createImportEmployee();
    $payload = [
        'employee_ids' => [$employeeId],
        'office_location_id' => $office->getKey(),
    ];

    $this->actingAs($superAdmin)
        ->post(route('users.import.store'), $payload)
        ->assertRedirect(route('users.import.create'))
        ->assertSessionDoesntHaveErrors();

    $this->actingAs($superAdmin)
        ->from(route('users.import.create'))
        ->post(route('users.import.store'), $payload)
        ->assertRedirect(route('users.import.create'))
        ->assertSessionHasErrors('employee_ids');

    $this->assertDatabaseCount('users', 2);
    $this->assertDatabaseCount('employee_user_links', 1);
});

test('non super admin cannot preview or run employee import', function () {
    $hrAdmin = User::factory()->hrAdmin()->create();
    $employeeId = createImportEmployee();
    $office = createImportOffice();

    $this->actingAs($hrAdmin)
        ->get(route('users.import.create'))
        ->assertForbidden();

    $this->actingAs($hrAdmin)
        ->post(route('users.import.store'), [
            'employee_ids' => [$employeeId],
            'office_location_id' => $office->getKey(),
        ])
        ->assertForbidden();

    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('employee_user_links', 0);
});
