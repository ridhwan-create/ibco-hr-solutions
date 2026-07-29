<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        $table->string('nric')->nullable();
        $table->string('employeeID', 15)->nullable();
        $table->string('nama')->nullable();
        $table->string('alamat')->nullable();
        $table->string('jantina', 1)->nullable();
        $table->date('tarikhlahir')->nullable();
        $table->string('agama', 1)->nullable();
        $table->string('bangsa', 1)->nullable();
        $table->string('kewarganegaraan')->nullable();
        $table->string('statusperkahwinan', 2)->nullable();
        $table->string('notel', 20)->nullable();
        $table->string('email')->nullable();
        $table->string('status', 1)->nullable();
        $table->boolean('rcd_enable')->default(true);
        $table->string('crt_by', 12)->nullable();
        $table->date('crt_dt')->nullable();
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
        $table->string('id', 1)->primary();
        $table->string('description');
        $table->boolean('rcd_enable')->default(true);
    });

    DB::connection('ibco')->table('xjantina')->insert([
        'id' => 1,
        'description' => 'Lelaki',
        'rcd_enable' => 1,
    ]);
    DB::connection('ibco')->table('xagama')->insert([
        'id' => 1,
        'description' => 'Islam',
        'rcd_enable' => 1,
    ]);
    DB::connection('ibco')->table('xbangsa')->insert([
        'id' => 1,
        'description' => 'Melayu',
        'rcd_enable' => 1,
    ]);
    DB::connection('ibco')->table('xstatus')->insert([
        'id' => 1,
        'description' => 'Aktif',
        'rcd_enable' => 1,
    ]);
    DB::connection('ibco')->table('xstatusperkahwinan')->insert([
        'id' => 'B',
        'description' => 'Bujang',
        'rcd_enable' => 1,
    ]);
});

afterEach(function () {
    DB::disconnect('ibco');
});

function validEmployeePayload(array $overrides = []): array
{
    return [
        'employeeID' => 'EMP001',
        'nric' => '900101011234',
        'nama' => 'Pekerja Contoh',
        'alamat' => 'Kuala Lumpur',
        'jantina' => '1',
        'tarikhlahir' => '1990-01-01',
        'agama' => '1',
        'bangsa' => '1',
        'kewarganegaraan' => 'Malaysia',
        'statusperkahwinan' => 'B',
        'notel' => '0123456789',
        'email' => 'pekerja@example.com',
        'status' => '1',
        ...$overrides,
    ];
}

test('hr admin can create an employee and an audit log is recorded', function () {
    $hrAdmin = User::factory()->hrAdmin()->create([
        'name' => 'Pentadbir HR Panjang',
    ]);

    $response = $this->actingAs($hrAdmin)
        ->post(route('pekerja.store'), validEmployeePayload());

    $employee = DB::connection('ibco')->table('maklumatpekerja')->first();

    $response->assertRedirect(route('pekerja.show', $employee->id));

    $this->assertDatabaseHas('maklumatpekerja', [
        'employeeID' => 'EMP001',
        'nric' => '900101011234',
        'nama' => 'Pekerja Contoh',
        'rcd_enable' => 1,
        'crt_by' => 'Pentadbir HR',
    ], 'ibco');

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $hrAdmin->id,
        'action' => 'employee.created',
        'auditable_type' => 'maklumatpekerja',
        'auditable_id' => (string) $employee->id,
    ]);
});

test('viewer cannot open or submit employee management pages', function () {
    $viewer = User::factory()->create();

    $this->actingAs($viewer)
        ->get(route('pekerja.create'))
        ->assertForbidden();

    $this->actingAs($viewer)
        ->post(route('pekerja.store'), validEmployeePayload())
        ->assertForbidden();

    $this->assertDatabaseCount('maklumatpekerja', 0, 'ibco');
});

test('employee id and nric duplicates are rejected', function () {
    $hrAdmin = User::factory()->hrAdmin()->create();
    DB::connection('ibco')->table('maklumatpekerja')->insert([
        ...validEmployeePayload(),
        'rcd_enable' => 1,
    ]);

    $this->actingAs($hrAdmin)
        ->from(route('pekerja.create'))
        ->post(route('pekerja.store'), validEmployeePayload([
            'nama' => 'Rekod Pendua',
        ]))
        ->assertRedirect(route('pekerja.create'))
        ->assertSessionHasErrors(['employeeID', 'nric']);

    $this->assertDatabaseCount('maklumatpekerja', 1, 'ibco');
});

test('hr admin can update an active employee and changes are audited', function () {
    $hrAdmin = User::factory()->hrAdmin()->create();
    $employeeId = DB::connection('ibco')->table('maklumatpekerja')->insertGetId([
        ...validEmployeePayload(),
        'rcd_enable' => 1,
    ]);

    $this->actingAs($hrAdmin)
        ->put(route('pekerja.update', $employeeId), validEmployeePayload([
            'nama' => 'Nama Dikemas Kini',
            'notel' => '0198765432',
        ]))
        ->assertRedirect(route('pekerja.show', $employeeId));

    $this->assertDatabaseHas('maklumatpekerja', [
        'id' => $employeeId,
        'nama' => 'Nama Dikemas Kini',
        'notel' => '0198765432',
    ], 'ibco');

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $hrAdmin->id,
        'action' => 'employee.updated',
        'auditable_id' => (string) $employeeId,
    ]);
});

test('destroy only deactivates an employee and records an audit log', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $employeeId = DB::connection('ibco')->table('maklumatpekerja')->insertGetId([
        ...validEmployeePayload(),
        'rcd_enable' => 1,
    ]);

    $this->actingAs($superAdmin)
        ->delete(route('pekerja.destroy', $employeeId))
        ->assertRedirect(route('pekerja.index'));

    $this->assertDatabaseHas('maklumatpekerja', [
        'id' => $employeeId,
        'rcd_enable' => 0,
    ], 'ibco');

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $superAdmin->id,
        'action' => 'employee.deactivated',
        'auditable_id' => (string) $employeeId,
    ]);
});
