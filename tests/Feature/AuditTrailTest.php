<?php

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        $table->string('nric')->nullable();
        $table->string('notel', 20)->nullable();
        $table->string('email')->nullable();
        $table->string('status', 2)->nullable();
        $table->boolean('rcd_enable')->default(true);
        $table->string('mdf_by', 12)->nullable();
        $table->date('mdf_dt')->nullable();
    });

    foreach (['xjantina', 'xagama', 'xbangsa', 'xstatus'] as $tableName) {
        Schema::connection('ibco')->create($tableName, function (Blueprint $table) {
            $table->increments('id');
            $table->string('description');
            $table->boolean('rcd_enable')->default(true);
        });
    }

    Schema::connection('ibco')->create('xstatusperkahwinan', function (Blueprint $table) {
        $table->string('id', 2)->primary();
        $table->string('description');
        $table->boolean('rcd_enable')->default(true);
    });

    DB::connection('ibco')->table('xstatus')->insert([
        'id' => 1,
        'description' => 'Aktif',
        'rcd_enable' => 1,
    ]);

    foreach (['xdepartment', 'xbank'] as $tableName) {
        Schema::connection('ibco')->create($tableName, function (Blueprint $table) {
            $table->increments('id');
            $table->string('description');
            $table->boolean('rcd_enable')->default(true);
        });
    }

    Schema::connection('ibco')->create('maklumatjawatan', function (Blueprint $table) {
        $table->increments('id');
        $table->string('id_pekerja', 4)->nullable();
        $table->string('jawatan', 100)->nullable();
        $table->string('id_department', 2)->nullable();
        $table->boolean('rcd_enable')->default(true);
    });
});

afterEach(function () {
    DB::disconnect('ibco');
});

function createAuditEmployee(array $overrides = []): int
{
    return DB::connection('ibco')->table('maklumatpekerja')->insertGetId([
        'employeeID' => 'EMP-AUDIT',
        'nama' => 'Pekerja Audit',
        'nric' => '900101011234',
        'notel' => '0123456789',
        'email' => 'audit@example.com',
        'status' => 1,
        'rcd_enable' => 0,
        'mdf_by' => 'Pentadbir',
        'mdf_dt' => '2026-07-28',
        ...$overrides,
    ]);
}

test('super admin and hr admin can open the audit trail', function (UserRole $role) {
    $user = User::factory()->create(['role' => $role]);
    $employeeId = createAuditEmployee();

    AuditLog::query()->create([
        'user_id' => $user->id,
        'action' => 'employee.deactivated',
        'auditable_type' => 'maklumatpekerja',
        'auditable_id' => (string) $employeeId,
        'old_values' => ['rcd_enable' => 1],
        'new_values' => ['rcd_enable' => 0],
        'ip_address' => '127.0.0.1',
    ]);

    $this->actingAs($user)
        ->get(route('audit.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AuditTrail/Index')
            ->has('audits.data', 1)
            ->where('audits.data.0.action', 'employee.deactivated')
            ->where('inactiveEmployees.total', 1));
})->with([
    'Super Admin' => UserRole::SuperAdmin,
    'HR Admin' => UserRole::HrAdmin,
]);

test('viewer cannot access the audit trail', function () {
    $viewer = User::factory()->create(['role' => UserRole::Viewer]);

    $this->actingAs($viewer)
        ->get(route('audit.index'))
        ->assertForbidden();
});

test('audit records can be filtered by action and user', function () {
    $hrAdmin = User::factory()->hrAdmin()->create();
    $otherAdmin = User::factory()->superAdmin()->create();
    $employeeId = createAuditEmployee();

    AuditLog::query()->create([
        'user_id' => $hrAdmin->id,
        'action' => 'employee.updated',
        'auditable_type' => 'maklumatpekerja',
        'auditable_id' => (string) $employeeId,
        'old_values' => ['nama' => 'Nama Lama'],
        'new_values' => ['nama' => 'Nama Baharu'],
    ]);
    AuditLog::query()->create([
        'user_id' => $otherAdmin->id,
        'action' => 'employee.deactivated',
        'auditable_type' => 'maklumatpekerja',
        'auditable_id' => (string) $employeeId,
        'old_values' => ['rcd_enable' => 1],
        'new_values' => ['rcd_enable' => 0],
    ]);

    $this->actingAs($hrAdmin)
        ->get(route('audit.index', [
            'action' => 'employee.updated',
            'user_id' => $hrAdmin->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('audits.data', 1)
            ->where('audits.data.0.action', 'employee.updated')
            ->where('audits.data.0.user.id', $hrAdmin->id));
});

test('multiple role arrays are displayed using readable role labels', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    AuditLog::query()->create([
        'user_id' => $superAdmin->id,
        'action' => 'user.updated',
        'auditable_type' => 'users',
        'auditable_id' => (string) $superAdmin->id,
        'old_values' => ['roles' => ['employee']],
        'new_values' => ['roles' => ['super_admin', 'employee']],
    ]);

    $this->actingAs($superAdmin)
        ->get(route('audit.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('audits.data.0.old_values.roles', 'Employee')
            ->where(
                'audits.data.0.new_values.roles',
                'Super Admin, Employee',
            ));
});

test('position changes appear in the audit trail with the related employee', function () {
    $hrAdmin = User::factory()->hrAdmin()->create();
    $employeeId = createAuditEmployee(['rcd_enable' => 1]);

    DB::connection('ibco')->table('xdepartment')->insert([
        'id' => 1,
        'description' => 'Teknologi Maklumat',
        'rcd_enable' => 1,
    ]);
    $positionId = DB::connection('ibco')->table('maklumatjawatan')->insertGetId([
        'id_pekerja' => $employeeId,
        'jawatan' => 'Pengurus IT',
        'id_department' => 1,
        'rcd_enable' => 1,
    ]);

    AuditLog::query()->create([
        'user_id' => $hrAdmin->id,
        'action' => 'position.changed',
        'auditable_type' => 'maklumatjawatan',
        'auditable_id' => (string) $positionId,
        'old_values' => [
            'jawatan' => 'Eksekutif IT',
            'id_department' => 1,
        ],
        'new_values' => [
            'jawatan' => 'Pengurus IT',
            'id_department' => 1,
        ],
    ]);

    $this->actingAs($hrAdmin)
        ->get(route('audit.index', ['action' => 'position.changed']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('audits.data', 1)
            ->where('audits.data.0.action', 'position.changed')
            ->where('audits.data.0.employee.id', $employeeId)
            ->where('audits.data.0.new_values.id_department', 'Teknologi Maklumat'));
});

test('hr admin can reactivate an employee and the action is audited', function () {
    $hrAdmin = User::factory()->hrAdmin()->create([
        'name' => 'Pentadbir HR Panjang',
    ]);
    $employeeId = createAuditEmployee();

    $this->actingAs($hrAdmin)
        ->patch(route('audit.employees.restore', $employeeId))
        ->assertRedirect(route('audit.index', ['tab' => 'inactive']));

    $this->assertDatabaseHas('maklumatpekerja', [
        'id' => $employeeId,
        'rcd_enable' => 1,
        'mdf_by' => 'Pentadbir HR',
    ], 'ibco');

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $hrAdmin->id,
        'action' => 'employee.reactivated',
        'auditable_type' => 'maklumatpekerja',
        'auditable_id' => (string) $employeeId,
    ]);
});

test('viewer cannot reactivate an employee', function () {
    $viewer = User::factory()->create(['role' => UserRole::Viewer]);
    $employeeId = createAuditEmployee();

    $this->actingAs($viewer)
        ->patch(route('audit.employees.restore', $employeeId))
        ->assertForbidden();

    $this->assertDatabaseHas('maklumatpekerja', [
        'id' => $employeeId,
        'rcd_enable' => 0,
    ], 'ibco');
    $this->assertDatabaseCount('audit_logs', 0);
});

test('an active employee cannot be reactivated', function () {
    $hrAdmin = User::factory()->hrAdmin()->create();
    $employeeId = createAuditEmployee(['rcd_enable' => 1]);

    $this->actingAs($hrAdmin)
        ->patch(route('audit.employees.restore', $employeeId))
        ->assertNotFound();

    $this->assertDatabaseCount('audit_logs', 0);
});
