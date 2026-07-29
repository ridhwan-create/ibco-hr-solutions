<?php

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
        $table->boolean('rcd_enable')->default(true);
    });

    Schema::connection('ibco')->create('xdepartment', function (Blueprint $table) {
        $table->increments('id');
        $table->string('description');
        $table->boolean('rcd_enable')->default(true);
    });

    Schema::connection('ibco')->create('xbank', function (Blueprint $table) {
        $table->increments('id');
        $table->string('description');
        $table->boolean('rcd_enable')->default(true);
    });

    Schema::connection('ibco')->create('maklumatjawatan', function (Blueprint $table) {
        $table->increments('id');
        $table->string('id_pekerja', 4)->nullable();
        $table->date('date_lapordiri')->nullable();
        $table->date('date_tempohcubaan')->nullable();
        $table->string('id_department', 1)->nullable();
        $table->string('jawatan', 100)->nullable();
        $table->decimal('salary', 10, 2)->nullable();
        $table->string('id_bank', 4)->nullable();
        $table->string('noakaun', 20)->nullable();
        $table->string('noepf', 20)->nullable();
        $table->string('nosocso', 20)->nullable();
        $table->string('jumlahcuti', 20)->nullable();
        $table->boolean('rcd_enable')->default(true);
        $table->string('crt_by', 12)->nullable();
        $table->date('crt_dt')->nullable();
        $table->string('mdf_by', 12)->nullable();
        $table->date('mdf_dt')->nullable();
    });

    DB::connection('ibco')->table('xdepartment')->insert([
        ['id' => 1, 'description' => 'Sumber Manusia', 'rcd_enable' => 1],
        ['id' => 2, 'description' => 'Teknologi Maklumat', 'rcd_enable' => 1],
    ]);
    DB::connection('ibco')->table('xbank')->insert([
        'id' => 1,
        'description' => 'Maybank',
        'rcd_enable' => 1,
    ]);
});

afterEach(function () {
    DB::disconnect('ibco');
});

function createPositionEmployee(array $overrides = []): int
{
    return DB::connection('ibco')->table('maklumatpekerja')->insertGetId([
        'employeeID' => 'EMP001',
        'nama' => 'Pekerja Jawatan',
        'rcd_enable' => 1,
        ...$overrides,
    ]);
}

function validPositionPayload(int $employeeId, array $overrides = []): array
{
    return [
        'id_pekerja' => $employeeId,
        'date_lapordiri' => '2026-07-01',
        'date_tempohcubaan' => '2026-10-01',
        'id_department' => 1,
        'jawatan' => 'Eksekutif HR',
        'salary' => '3500.00',
        'id_bank' => 1,
        'noakaun' => '1234567890',
        'noepf' => 'EPF001',
        'nosocso' => 'SOCSO001',
        'jumlahcuti' => 20,
        ...$overrides,
    ];
}

function createPositionRecord(int $employeeId, array $overrides = []): int
{
    return DB::connection('ibco')->table('maklumatjawatan')->insertGetId([
        ...validPositionPayload($employeeId),
        'rcd_enable' => 1,
        'crt_by' => 'Pentadbir',
        'crt_dt' => '2026-07-01',
        ...$overrides,
    ]);
}

test('hr admin can add a first placement and the action is audited', function () {
    $hrAdmin = User::factory()->hrAdmin()->create([
        'name' => 'Pentadbir HR Panjang',
    ]);
    $employeeId = createPositionEmployee();

    $response = $this->actingAs($hrAdmin)
        ->post(route('jawatan.store'), validPositionPayload($employeeId));

    $position = DB::connection('ibco')->table('maklumatjawatan')->first();

    $response->assertRedirect(route('jawatan.show', $position->id));

    $this->assertDatabaseHas('maklumatjawatan', [
        'id_pekerja' => (string) $employeeId,
        'jawatan' => 'Eksekutif HR',
        'rcd_enable' => 1,
        'crt_by' => 'Pentadbir HR',
    ], 'ibco');

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $hrAdmin->id,
        'action' => 'position.created',
        'auditable_type' => 'maklumatjawatan',
        'auditable_id' => (string) $position->id,
    ]);
});

test('adding a new placement archives every active legacy placement', function () {
    $hrAdmin = User::factory()->hrAdmin()->create();
    $employeeId = createPositionEmployee();
    createPositionRecord($employeeId, ['jawatan' => 'Jawatan Lama 1']);
    createPositionRecord($employeeId, ['jawatan' => 'Jawatan Lama 2']);

    $this->actingAs($hrAdmin)
        ->post(route('jawatan.store'), validPositionPayload($employeeId, [
            'jawatan' => 'Jawatan Baharu',
            'date_lapordiri' => '2026-08-01',
        ]))
        ->assertRedirect();

    expect(
        DB::connection('ibco')->table('maklumatjawatan')
            ->where('id_pekerja', $employeeId)
            ->where('rcd_enable', 1)
            ->count(),
    )->toBe(1);

    $this->assertDatabaseHas('maklumatjawatan', [
        'id_pekerja' => (string) $employeeId,
        'jawatan' => 'Jawatan Baharu',
        'rcd_enable' => 1,
    ], 'ibco');

    $this->assertDatabaseCount('maklumatjawatan', 3, 'ibco');
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'position.changed',
        'auditable_type' => 'maklumatjawatan',
    ]);
});

test('updating a position creates a new record and preserves the old record', function () {
    $hrAdmin = User::factory()->hrAdmin()->create();
    $employeeId = createPositionEmployee();
    $oldPositionId = createPositionRecord($employeeId);

    $this->actingAs($hrAdmin)
        ->put(route('jawatan.update', $oldPositionId), validPositionPayload(
            $employeeId,
            [
                'jawatan' => 'Pengurus Sumber Manusia',
                'id_department' => 2,
                'salary' => '5000.00',
                'date_lapordiri' => '2026-08-01',
            ],
        ))
        ->assertRedirect();

    $this->assertDatabaseHas('maklumatjawatan', [
        'id' => $oldPositionId,
        'jawatan' => 'Eksekutif HR',
        'rcd_enable' => 0,
    ], 'ibco');
    $this->assertDatabaseHas('maklumatjawatan', [
        'id_pekerja' => (string) $employeeId,
        'jawatan' => 'Pengurus Sumber Manusia',
        'id_department' => '2',
        'rcd_enable' => 1,
    ], 'ibco');
    $this->assertDatabaseCount('maklumatjawatan', 2, 'ibco');
    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $hrAdmin->id,
        'action' => 'position.changed',
        'auditable_type' => 'maklumatjawatan',
    ]);
});

test('terminating a position only moves it to history', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $employeeId = createPositionEmployee();
    $positionId = createPositionRecord($employeeId);

    $this->actingAs($superAdmin)
        ->delete(route('jawatan.destroy', $positionId))
        ->assertRedirect(route('jawatan.index', ['status' => 'history']));

    $this->assertDatabaseHas('maklumatjawatan', [
        'id' => $positionId,
        'rcd_enable' => 0,
    ], 'ibco');
    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $superAdmin->id,
        'action' => 'position.terminated',
        'auditable_id' => (string) $positionId,
    ]);
});

test('viewer can view positions but cannot receive payroll fields or manage them', function () {
    $viewer = User::factory()->create();
    $employeeId = createPositionEmployee();
    $positionId = createPositionRecord($employeeId);

    $this->actingAs($viewer)
        ->get(route('jawatan.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('MaklumatJawatan/Index')
            ->where('canManage', false)
            ->where('canViewPayroll', false)
            ->missing('records.data.0.gaji_asas')
            ->missing('records.data.0.bank'));

    $this->actingAs($viewer)
        ->get(route('jawatan.show', $positionId))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('MaklumatJawatan/Show')
            ->missing('jawatan.gaji_asas')
            ->missing('history.0.gaji_asas'));

    $this->actingAs($viewer)
        ->get(route('jawatan.create'))
        ->assertForbidden();

    $this->actingAs($viewer)
        ->post(route('jawatan.store'), validPositionPayload($employeeId))
        ->assertForbidden();

    $this->actingAs($viewer)
        ->delete(route('jawatan.destroy', $positionId))
        ->assertForbidden();
});

test('invalid probation date and inactive references are rejected', function () {
    $hrAdmin = User::factory()->hrAdmin()->create();
    $employeeId = createPositionEmployee();

    DB::connection('ibco')->table('xdepartment')
        ->where('id', 2)
        ->update(['rcd_enable' => 0]);

    $this->actingAs($hrAdmin)
        ->from(route('jawatan.create'))
        ->post(route('jawatan.store'), validPositionPayload($employeeId, [
            'date_lapordiri' => '2026-08-01',
            'date_tempohcubaan' => '2026-07-01',
            'id_department' => 2,
        ]))
        ->assertRedirect(route('jawatan.create'))
        ->assertSessionHasErrors([
            'date_tempohcubaan',
            'id_department',
        ]);

    $this->assertDatabaseCount('maklumatjawatan', 0, 'ibco');
    $this->assertDatabaseCount('audit_logs', 0);
});
