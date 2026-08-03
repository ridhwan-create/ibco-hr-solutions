<?php

use App\Enums\UserRole;
use App\Models\EmployeeLeaveRequest;
use App\Models\EmployeeUserLink;
use App\Models\OfficeLocation;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-07-29 10:00:00');

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
        $table->string('alamat')->nullable();
        $table->string('notel', 30)->nullable();
        $table->string('email')->nullable();
        $table->date('tarikhlahir')->nullable();
        $table->string('kewarganegaraan')->nullable();
        $table->integer('jantina')->nullable();
        $table->integer('agama')->nullable();
        $table->integer('bangsa')->nullable();
        $table->string('statusperkahwinan')->nullable();
        $table->integer('status')->nullable();
        $table->boolean('rcd_enable')->default(true);
    });

    foreach ([
        'xjantina',
        'xagama',
        'xbangsa',
        'xstatusperkahwinan',
        'xstatus',
        'xdepartment',
        'xsenaraicuti',
        'xstatuscuti',
    ] as $tableName) {
        Schema::connection('ibco')->create($tableName, function (Blueprint $table) {
            $table->increments('id');
            $table->string('description')->nullable();
            $table->boolean('rcd_enable')->default(true);
        });
    }

    Schema::connection('ibco')->create('maklumatjawatan', function (Blueprint $table) {
        $table->increments('id');
        $table->integer('id_pekerja')->nullable();
        $table->integer('id_department')->nullable();
        $table->string('jawatan')->nullable();
        $table->date('date_lapordiri')->nullable();
        $table->string('jumlahcuti')->nullable();
        $table->boolean('rcd_enable')->default(true);
    });

    Schema::connection('ibco')->create('maklumatcuti', function (Blueprint $table) {
        $table->increments('id');
        $table->integer('id_pekerja')->nullable();
        $table->string('tahun', 4)->nullable();
        $table->integer('jenis_cuti')->nullable();
        $table->date('date_mulacuti')->nullable();
        $table->date('date_tamatcuti')->nullable();
        $table->string('bil_cutidipohon')->nullable();
        $table->string('bakicuti')->nullable();
        $table->integer('status_permohonan')->nullable();
        $table->boolean('rcd_enable')->default(true);
    });

    DB::connection('ibco')->table('xjantina')->insert([
        'id' => 1,
        'description' => 'Perempuan',
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
    DB::connection('ibco')->table('xstatusperkahwinan')->insert([
        'id' => 1,
        'description' => 'Berkahwin',
        'rcd_enable' => 1,
    ]);
    DB::connection('ibco')->table('xstatus')->insert([
        'id' => 1,
        'description' => 'Aktif',
        'rcd_enable' => 1,
    ]);
    DB::connection('ibco')->table('xdepartment')->insert([
        'id' => 1,
        'description' => 'Teknologi Maklumat',
        'rcd_enable' => 1,
    ]);
    DB::connection('ibco')->table('xsenaraicuti')->insert([
        'id' => 1,
        'description' => 'Cuti Tahunan',
        'rcd_enable' => 1,
    ]);
    DB::connection('ibco')->table('xstatuscuti')->insert([
        'id' => 1,
        'description' => 'Diluluskan',
        'rcd_enable' => 1,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
    DB::disconnect('ibco');
});

function createSelfServiceEmployee(array $overrides = []): int
{
    $employeeId = DB::connection('ibco')->table('maklumatpekerja')->insertGetId([
        'employeeID' => 'EMP-ESS-001',
        'nama' => 'Pekerja ESS',
        'nric' => '900101011234',
        'alamat' => 'Alamat Asal',
        'notel' => '0123456789',
        'email' => 'asal@ibco.test',
        'tarikhlahir' => '1990-01-01',
        'kewarganegaraan' => 'Malaysia',
        'jantina' => 1,
        'agama' => 1,
        'bangsa' => 1,
        'statusperkahwinan' => 1,
        'status' => 1,
        'rcd_enable' => 1,
        ...$overrides,
    ]);

    DB::connection('ibco')->table('maklumatjawatan')->insert([
        'id_pekerja' => $employeeId,
        'id_department' => 1,
        'jawatan' => 'Pegawai Teknologi Maklumat',
        'date_lapordiri' => '2020-01-01',
        'jumlahcuti' => '20',
        'rcd_enable' => 1,
    ]);

    DB::connection('ibco')->table('maklumatcuti')->insert([
        'id_pekerja' => $employeeId,
        'tahun' => '2026',
        'jenis_cuti' => 1,
        'date_mulacuti' => '2026-01-05',
        'date_tamatcuti' => '2026-01-06',
        'bil_cutidipohon' => '2',
        'bakicuti' => '18',
        'status_permohonan' => 1,
        'rcd_enable' => 1,
    ]);

    return $employeeId;
}

function linkSelfServiceEmployee(User $user, int $employeeId): EmployeeUserLink
{
    $office = OfficeLocation::query()->create([
        'name' => 'IBCO Solutions HQ',
        'address' => 'Kuala Lumpur',
        'latitude' => 3.1390000,
        'longitude' => 101.6869000,
        'radius_meters' => 100,
        'accuracy_limit_meters' => 100,
        'is_active' => true,
    ]);

    return EmployeeUserLink::query()->create([
        'user_id' => $user->getKey(),
        'employee_id' => $employeeId,
        'office_location_id' => $office->getKey(),
        'is_active' => true,
    ]);
}

test('employee can view self service profile and leave data using select queries only on db_spp', function () {
    $user = User::factory()->employee()->create();
    $employeeId = createSelfServiceEmployee();
    linkSelfServiceEmployee($user, $employeeId);
    $legacyQueries = [];

    DB::listen(function (QueryExecuted $event) use (&$legacyQueries) {
        if ($event->connectionName === 'ibco') {
            $legacyQueries[] = strtolower(trim($event->sql));
        }
    });

    $this->actingAs($user)
        ->get(route('employee-profile.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('EmployeeSelfService/Profile')
            ->where('employee.id', $employeeId)
            ->where('contact.phone', '0123456789')
            ->where('position.leave_entitlement', '20'));

    $this->actingAs($user)
        ->get(route('employee-leave.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('EmployeeSelfService/Leave')
            ->where('employee.id', $employeeId)
            ->where('summary.legacy_balance', '18')
            ->where('leaveTypes.0.label', 'Cuti Tahunan'));

    expect($legacyQueries)->not->toBeEmpty();
    expect($legacyQueries)->each->toStartWith('select');
});

test('employee updates contact information locally without changing db_spp', function () {
    $user = User::factory()->employee()->create();
    $employeeId = createSelfServiceEmployee();
    linkSelfServiceEmployee($user, $employeeId);
    $legacyBefore = DB::connection('ibco')
        ->table('maklumatpekerja')
        ->where('id', $employeeId)
        ->first();

    $this->actingAs($user)
        ->put(route('employee-profile.update'), [
            'address' => 'Alamat Baharu',
            'phone' => '0198765432',
            'email' => 'baharu@ibco.test',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    $this->assertDatabaseHas('employee_personal_profiles', [
        'user_id' => $user->getKey(),
        'employee_id' => $employeeId,
        'address' => 'Alamat Baharu',
        'phone' => '0198765432',
        'email' => 'baharu@ibco.test',
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'employee.profile_updated',
        'auditable_type' => 'employee_personal_profiles',
    ]);
    expect(
        DB::connection('ibco')
            ->table('maklumatpekerja')
            ->where('id', $employeeId)
            ->first(),
    )->toEqual($legacyBefore);
});

test('employee can submit leave and working days are calculated on the server', function () {
    $user = User::factory()->employee()->create();
    $employeeId = createSelfServiceEmployee();
    linkSelfServiceEmployee($user, $employeeId);
    $legacyBefore = DB::connection('ibco')->table('maklumatcuti')->get();

    $this->actingAs($user)
        ->post(route('employee-leave.store'), [
            'leave_type_id' => 1,
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-05',
            'reason' => 'Urusan keluarga',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    $this->assertDatabaseHas('employee_leave_requests', [
        'user_id' => $user->getKey(),
        'employee_id' => $employeeId,
        'leave_type_label' => 'Cuti Tahunan',
        'requested_days' => 3,
        'status' => 'pending',
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'leave.submitted',
        'auditable_type' => 'employee_leave_requests',
    ]);
    expect(DB::connection('ibco')->table('maklumatcuti')->get())
        ->toEqual($legacyBefore);
});

test('overlapping active leave application is rejected', function () {
    $user = User::factory()->employee()->create();
    $employeeId = createSelfServiceEmployee();
    linkSelfServiceEmployee($user, $employeeId);

    EmployeeLeaveRequest::query()->create([
        'user_id' => $user->getKey(),
        'employee_id' => $employeeId,
        'leave_type_id' => 1,
        'leave_type_label' => 'Cuti Tahunan',
        'start_date' => '2026-08-03',
        'end_date' => '2026-08-05',
        'requested_days' => 3,
        'reason' => 'Permohonan pertama',
        'status' => 'pending',
        'submitted_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('employee-leave.store'), [
            'leave_type_id' => 1,
            'start_date' => '2026-08-05',
            'end_date' => '2026-08-07',
            'reason' => 'Permohonan bertindih',
        ])
        ->assertSessionHasErrors('start_date');

    $this->assertDatabaseCount('employee_leave_requests', 1);
});

test('hr manager can approve leave while ordinary employee cannot access approval list', function () {
    $employeeUser = User::factory()->employee()->create();
    $employeeId = createSelfServiceEmployee();
    linkSelfServiceEmployee($employeeUser, $employeeId);
    $leave = EmployeeLeaveRequest::query()->create([
        'user_id' => $employeeUser->getKey(),
        'employee_id' => $employeeId,
        'leave_type_id' => 1,
        'leave_type_label' => 'Cuti Tahunan',
        'start_date' => '2026-08-03',
        'end_date' => '2026-08-05',
        'requested_days' => 3,
        'reason' => 'Urusan keluarga',
        'status' => 'pending',
        'submitted_at' => now(),
    ]);
    $hrAdmin = User::factory()->hrAdmin()->create();
    $hrManager = User::factory()->hrManager()->create();

    $this->actingAs($employeeUser)
        ->get(route('leave-requests.index'))
        ->assertForbidden();

    $this->actingAs($hrAdmin)
        ->get(route('leave-requests.index'))
        ->assertOk();

    $this->actingAs($hrManager)
        ->patch(route('leave-requests.review', $leave), [
            'status' => 'approved',
            'review_notes' => 'Permohonan lengkap.',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    expect($leave->fresh()->status)->toBe('approved');
    expect($leave->fresh()->reviewed_by)->toBe($hrManager->getKey());
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'leave.approved',
        'auditable_type' => 'employee_leave_requests',
        'auditable_id' => (string) $leave->getKey(),
    ]);
});

test('employee can cancel only their own pending leave request', function () {
    $employeeUser = User::factory()->employee()->create();
    $employeeId = createSelfServiceEmployee();
    linkSelfServiceEmployee($employeeUser, $employeeId);
    $leave = EmployeeLeaveRequest::query()->create([
        'user_id' => $employeeUser->getKey(),
        'employee_id' => $employeeId,
        'leave_type_id' => 1,
        'leave_type_label' => 'Cuti Tahunan',
        'start_date' => '2026-08-03',
        'end_date' => '2026-08-05',
        'requested_days' => 3,
        'reason' => 'Urusan keluarga',
        'status' => 'pending',
        'submitted_at' => now(),
    ]);
    $otherEmployee = User::factory()->employee()->create();

    $this->actingAs($otherEmployee)
        ->patch(route('employee-leave.cancel', $leave))
        ->assertForbidden();

    $this->actingAs($employeeUser)
        ->patch(route('employee-leave.cancel', $leave))
        ->assertRedirect();

    expect($leave->fresh()->status)->toBe('cancelled');
});

test('multiple role user receives employee self service permissions from employee role', function () {
    $user = User::factory()->superAdmin()->create();
    $user->syncRoles([UserRole::SuperAdmin, UserRole::Employee]);

    expect($user->hasPermission('employee.profile.view'))->toBeTrue();
    expect($user->hasPermission('employee.profile.update'))->toBeTrue();
    expect($user->hasPermission('leave.self'))->toBeTrue();
    expect($user->hasPermission('leave.apply'))->toBeTrue();
    expect($user->hasPermission('leave.manage'))->toBeTrue();
});
