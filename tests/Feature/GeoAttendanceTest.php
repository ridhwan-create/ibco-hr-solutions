<?php

use App\Enums\UserRole;
use App\Models\AttendanceAdjustment;
use App\Models\EmployeeUserLink;
use App\Models\GeoAttendanceRecord;
use App\Models\OfficeLocation;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('app.timezone', 'Asia/Kuala_Lumpur');
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
        $table->boolean('rcd_enable')->default(true);
    });

    Schema::connection('ibco')->create('maklumatjawatan', function (
        Blueprint $table,
    ) {
        $table->increments('id');
        $table->integer('id_pekerja')->nullable();
        $table->integer('id_department')->nullable();
        $table->boolean('rcd_enable')->default(true);
    });

    Schema::connection('ibco')->create('xpilihanjam', function (Blueprint $table) {
        $table->increments('id');
        $table->string('description')->nullable();
        $table->boolean('rcd_enable')->default(true);
    });

    Schema::connection('ibco')->create('maklumatkehadiran', function (Blueprint $table) {
        $table->increments('id');
        $table->string('id_pekerja', 4)->nullable();
        $table->string('pilihan_jam', 2)->nullable();
        $table->dateTime('waktu_masuk')->nullable();
        $table->dateTime('waktu_keluar')->nullable();
        $table->string('catatan')->nullable();
        $table->boolean('rcd_enable')->default(true);
    });
});

afterEach(function () {
    DB::disconnect('ibco');
});

function createGeoEmployee(array $overrides = []): int
{
    return DB::connection('ibco')->table('maklumatpekerja')->insertGetId([
        'employeeID' => 'EMP-GEO-001',
        'nama' => 'Pekerja Geolocation',
        'nric' => '900101011234',
        'rcd_enable' => 1,
        ...$overrides,
    ]);
}

function createGeoOffice(array $overrides = []): OfficeLocation
{
    return OfficeLocation::query()->create([
        'name' => 'IBCO Solutions HQ',
        'address' => 'Kuala Lumpur',
        'latitude' => 3.1390000,
        'longitude' => 101.6869000,
        'radius_meters' => 100,
        'accuracy_limit_meters' => 100,
        'is_active' => true,
        ...$overrides,
    ]);
}

function linkGeoEmployee(
    User $user,
    int $employeeId,
    ?OfficeLocation $office = null,
): EmployeeUserLink {
    $office ??= createGeoOffice();

    return EmployeeUserLink::query()->create([
        'user_id' => $user->getKey(),
        'employee_id' => $employeeId,
        'office_location_id' => $office->getKey(),
        'is_active' => true,
    ]);
}

test('employee can open mobile attendance and db_spp receives select queries only', function () {
    $employeeUser = User::factory()->employee()->create();
    $employeeId = createGeoEmployee();
    linkGeoEmployee($employeeUser, $employeeId);
    $legacyQueries = [];

    DB::listen(function (QueryExecuted $event) use (&$legacyQueries) {
        if ($event->connectionName === 'ibco') {
            $legacyQueries[] = strtolower(trim($event->sql));
        }
    });

    $this->actingAs($employeeUser)
        ->get(route('kehadiran.clock'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('GeoAttendance/Clock')
            ->where('employee.id', $employeeId)
            ->where('office.radius_meters', 100)
            ->where('todayRecord', null));

    expect($legacyQueries)->not->toBeEmpty();
    expect($legacyQueries)->each->toStartWith('select');
});

test('employee can clock in and out inside the configured radius', function () {
    $employeeUser = User::factory()->employee()->create();
    $employeeId = createGeoEmployee();
    linkGeoEmployee($employeeUser, $employeeId);
    $legacyBefore = DB::connection('ibco')->table('maklumatpekerja')->where('id', $employeeId)->first();

    $payload = [
        'latitude' => 3.1390000,
        'longitude' => 101.6869000,
        'accuracy' => 12,
    ];

    $this->actingAs($employeeUser)
        ->post(route('kehadiran.clock-in'), $payload)
        ->assertRedirect();

    $record = GeoAttendanceRecord::query()->sole();

    expect($record->employee_id)->toBe($employeeId);
    expect((float) $record->clock_in_distance_meters)->toBe(0.0);
    expect($record->clock_out_at)->toBeNull();

    $this->actingAs($employeeUser)
        ->post(route('kehadiran.clock-out'), $payload)
        ->assertRedirect();

    expect($record->fresh()->clock_out_at)->not->toBeNull();
    expect(DB::connection('ibco')->table('maklumatpekerja')->where('id', $employeeId)->first())
        ->toEqual($legacyBefore);
    $this->assertDatabaseHas('audit_logs', ['action' => 'attendance.clocked_in']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'attendance.clocked_out']);
});

test('clock in outside 100 metres is rejected', function () {
    $employeeUser = User::factory()->employee()->create();
    $employeeId = createGeoEmployee();
    linkGeoEmployee($employeeUser, $employeeId);

    $this->actingAs($employeeUser)
        ->post(route('kehadiran.clock-in'), [
            'latitude' => 3.1410000,
            'longitude' => 101.6869000,
            'accuracy' => 10,
        ])
        ->assertSessionHasErrors('location');

    $this->assertDatabaseCount('geo_attendance_records', 0);
});

test('inaccurate gps reading is rejected', function () {
    $employeeUser = User::factory()->employee()->create();
    $employeeId = createGeoEmployee();
    linkGeoEmployee($employeeUser, $employeeId);

    $this->actingAs($employeeUser)
        ->post(route('kehadiran.clock-in'), [
            'latitude' => 3.1390000,
            'longitude' => 101.6869000,
            'accuracy' => 150,
        ])
        ->assertSessionHasErrors('accuracy');

    $this->assertDatabaseCount('geo_attendance_records', 0);
});

test('duplicate daily clock in is prevented', function () {
    $employeeUser = User::factory()->employee()->create();
    $employeeId = createGeoEmployee();
    linkGeoEmployee($employeeUser, $employeeId);
    $payload = [
        'latitude' => 3.1390000,
        'longitude' => 101.6869000,
        'accuracy' => 10,
    ];

    $this->actingAs($employeeUser)->post(route('kehadiran.clock-in'), $payload);

    $this->actingAs($employeeUser)
        ->post(route('kehadiran.clock-in'), $payload)
        ->assertSessionHasErrors('attendance');

    $this->assertDatabaseCount('geo_attendance_records', 1);
});

test('unlinked employee account cannot record attendance', function () {
    $employeeUser = User::factory()->employee()->create();

    $this->actingAs($employeeUser)
        ->post(route('kehadiran.clock-in'), [
            'latitude' => 3.1390000,
            'longitude' => 101.6869000,
            'accuracy' => 10,
        ])
        ->assertSessionHasErrors('attendance');

    $this->assertDatabaseCount('geo_attendance_records', 0);
});

test('attendance administration respects role permissions', function () {
    $employeeUser = User::factory()->employee()->create();
    $viewer = User::factory()->create(['role' => UserRole::Viewer]);
    $hrAdmin = User::factory()->hrAdmin()->create();
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($employeeUser)->get(route('kehadiran.index'))->assertForbidden();
    $this->actingAs($employeeUser)->get(route('kehadiran.legacy'))->assertForbidden();
    $this->actingAs($viewer)->get(route('kehadiran.index'))->assertOk();
    $this->actingAs($viewer)->get(route('kehadiran.legacy'))->assertOk();
    $this->actingAs($hrAdmin)->get(route('kehadiran.index'))->assertOk();
    $this->actingAs($hrAdmin)->get(route('attendance-settings.index'))->assertForbidden();
    $this->actingAs($superAdmin)->get(route('attendance-settings.index'))->assertOk();
});

test('authorised user can view original attendance from db_spp using select queries only', function () {
    $viewer = User::factory()->create(['role' => UserRole::Viewer]);
    $employeeId = createGeoEmployee([
        'employeeID' => 'EMP-LEGACY-01',
        'nama' => 'Pekerja Rekod Asal',
    ]);
    $shiftId = DB::connection('ibco')->table('xpilihanjam')->insertGetId([
        'description' => 'Waktu Pejabat',
        'rcd_enable' => 1,
    ]);

    DB::connection('ibco')->table('maklumatkehadiran')->insert([
        'id_pekerja' => (string) $employeeId,
        'pilihan_jam' => (string) $shiftId,
        'waktu_masuk' => '2026-07-28 08:05:00',
        'waktu_keluar' => '2026-07-28 17:15:00',
        'catatan' => 'Rekod sistem asal',
        'rcd_enable' => 1,
    ]);

    $legacyQueries = [];

    DB::listen(function (QueryExecuted $event) use (&$legacyQueries) {
        if ($event->connectionName === 'ibco') {
            $legacyQueries[] = strtolower(trim($event->sql));
        }
    });

    $this->actingAs($viewer)
        ->get(route('kehadiran.legacy', ['search' => 'Rekod Asal']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('MaklumatKehadiran/Index')
            ->where('records.total', 1)
            ->where('records.data.0.employee_id', 'EMP-LEGACY-01')
            ->where('records.data.0.nama_pekerja', 'Pekerja Rekod Asal')
            ->where('records.data.0.pilihan_jam', 'Waktu Pejabat')
            ->where('records.data.0.catatan', 'Rekod sistem asal'));

    expect($legacyQueries)->not->toBeEmpty();
    expect($legacyQueries)->each->toStartWith('select');
});

test('super admin can create office and employee link without writing to db_spp', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $employeeUser = User::factory()->employee()->create();
    $employeeId = createGeoEmployee();
    $legacyQueries = [];

    $this->actingAs($superAdmin)
        ->post(route('attendance-settings.offices.store'), [
            'name' => 'Pejabat Cawangan',
            'address' => 'Putrajaya',
            'latitude' => 2.9264000,
            'longitude' => 101.6964000,
            'radius_meters' => 100,
            'accuracy_limit_meters' => 80,
        ])
        ->assertRedirect();

    $office = OfficeLocation::query()->sole();

    DB::listen(function (QueryExecuted $event) use (&$legacyQueries) {
        if ($event->connectionName === 'ibco') {
            $legacyQueries[] = strtolower(trim($event->sql));
        }
    });

    $this->actingAs($superAdmin)
        ->post(route('attendance-settings.links.store'), [
            'user_id' => $employeeUser->getKey(),
            'employee_id' => $employeeId,
            'office_location_id' => $office->getKey(),
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('employee_user_links', [
        'user_id' => $employeeUser->getKey(),
        'employee_id' => $employeeId,
        'office_location_id' => $office->getKey(),
        'is_active' => 1,
    ]);
    expect($legacyQueries)->not->toBeEmpty();
    expect($legacyQueries)->each->toStartWith('select');
});

test('hr admin can create and correct a manual attendance record with audit history', function () {
    $hrAdmin = User::factory()->hrAdmin()->create();
    $employeeId = createGeoEmployee();
    $office = createGeoOffice();

    $this->actingAs($hrAdmin)
        ->post(route('kehadiran.manual.store'), [
            'employee_id' => $employeeId,
            'office_location_id' => $office->getKey(),
            'attendance_date' => '2026-07-29',
            'clock_in_at' => '2026-07-29 08:30:00',
            'clock_out_at' => '2026-07-29 17:30:00',
            'reason' => 'Telefon pekerja rosak ketika hadir bertugas.',
        ])
        ->assertRedirect();

    $record = GeoAttendanceRecord::query()->sole();

    expect($record->source)->toBe('manual');
    $this->assertDatabaseHas('attendance_adjustments', [
        'geo_attendance_record_id' => $record->getKey(),
        'action' => 'manual_created',
    ]);

    $this->actingAs($hrAdmin)
        ->patch(route('kehadiran.adjust', $record), [
            'clock_in_at' => '2026-07-29 08:45:00',
            'clock_out_at' => '2026-07-29 17:30:00',
            'cancelled' => false,
            'reason' => 'Waktu masuk dibetulkan berdasarkan pengesahan penyelia.',
        ])
        ->assertRedirect();

    expect($record->fresh()->clock_in_at?->format('H:i'))->toBe('08:45');
    expect(AttendanceAdjustment::query()->where('action', 'corrected')->exists())->toBeTrue();
    $this->assertDatabaseHas('audit_logs', ['action' => 'attendance.manual_created']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'attendance.corrected']);
});

test('configured office radius is enforced by the server', function () {
    $employeeUser = User::factory()->employee()->create();
    $employeeId = createGeoEmployee();
    $office = createGeoOffice(['radius_meters' => 250]);
    linkGeoEmployee($employeeUser, $employeeId, $office);

    $this->actingAs($employeeUser)
        ->post(route('kehadiran.clock-in'), [
            'latitude' => 3.1405000,
            'longitude' => 101.6869000,
            'accuracy' => 10,
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    $this->assertDatabaseCount('geo_attendance_records', 1);
});
