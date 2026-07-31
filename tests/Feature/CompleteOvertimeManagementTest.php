<?php

use App\Models\AuditLog;
use App\Models\EmployeeUserLink;
use App\Models\GeoAttendanceRecord;
use App\Models\OfficeLocation;
use App\Models\OvertimeApprovalAssignment;
use App\Models\OvertimeNotification;
use App\Models\OvertimeRequest;
use App\Models\OvertimeType;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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
        $table->string('employeeID', 30)->nullable();
        $table->string('nama')->nullable();
        $table->boolean('rcd_enable')->default(true);
    });
    Schema::connection('ibco')->create('maklumatjawatan', function (Blueprint $table) {
        $table->increments('id');
        $table->integer('id_pekerja')->nullable();
        $table->integer('id_department')->nullable();
        $table->boolean('rcd_enable')->default(true);
    });
    Schema::connection('ibco')->create('xdepartment', function (Blueprint $table) {
        $table->increments('id');
        $table->string('description')->nullable();
        $table->boolean('rcd_enable')->default(true);
    });
    Schema::connection('ibco')->create('maklumatot', function (Blueprint $table) {
        $table->increments('id');
        $table->integer('id_pekerja')->nullable();
        $table->integer('jenis_ot')->nullable();
        $table->time('waktu_masuk')->nullable();
        $table->time('waktu_keluar')->nullable();
        $table->date('tarikh')->nullable();
        $table->string('catatan')->nullable();
        $table->boolean('rcd_enable')->default(true);
    });
    Schema::connection('ibco')->create('xjenisot', function (Blueprint $table) {
        $table->increments('id');
        $table->string('description')->nullable();
        $table->boolean('rcd_enable')->default(true);
    });

    DB::connection('ibco')->table('xdepartment')->insert([
        'id' => 1,
        'description' => 'Teknologi Maklumat',
        'rcd_enable' => 1,
    ]);
    DB::connection('ibco')->table('xjenisot')->insert([
        'id' => 1,
        'description' => 'Hari Bekerja',
        'rcd_enable' => 1,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
    DB::disconnect('ibco');
});

function createOvertimeEmployee(User $user, int $departmentId = 1): int
{
    $employeeId = DB::connection('ibco')->table('maklumatpekerja')->insertGetId([
        'employeeID' => 'EMP-OT-'.$user->getKey(),
        'nama' => 'Pekerja OT '.$user->getKey(),
        'rcd_enable' => 1,
    ]);
    DB::connection('ibco')->table('maklumatjawatan')->insert([
        'id_pekerja' => $employeeId,
        'id_department' => $departmentId,
        'rcd_enable' => 1,
    ]);
    $office = OfficeLocation::query()->create([
        'name' => 'IBCO Solutions HQ '.$user->getKey(),
        'address' => 'Kuala Lumpur',
        'latitude' => 3.139,
        'longitude' => 101.6869,
        'radius_meters' => 100,
        'accuracy_limit_meters' => 100,
        'is_active' => true,
    ]);
    EmployeeUserLink::query()->create([
        'user_id' => $user->getKey(),
        'employee_id' => $employeeId,
        'office_location_id' => $office->getKey(),
        'is_active' => true,
    ]);

    return $employeeId;
}

test('overtime follows supervisor then hr and retains approved payroll input', function () {
    $employeeUser = User::factory()->employee()->create();
    $supervisor = User::factory()->supervisor()->create();
    $hrAdmin = User::factory()->hrAdmin()->create();
    $employeeId = createOvertimeEmployee($employeeUser);
    $link = EmployeeUserLink::query()
        ->where('user_id', $employeeUser->getKey())
        ->firstOrFail();
    GeoAttendanceRecord::query()->create([
        'user_id' => $employeeUser->getKey(),
        'employee_id' => $employeeId,
        'office_location_id' => $link->office_location_id,
        'attendance_date' => '2026-07-29',
        'clock_in_at' => '2026-07-29 09:00:00',
        'clock_out_at' => '2026-07-29 20:30:00',
        'source' => 'geolocation',
        'status' => 'active',
    ]);
    OvertimeApprovalAssignment::query()->create([
        'department_id' => 1,
        'approver_user_id' => $supervisor->getKey(),
        'is_active' => true,
    ]);
    $type = OvertimeType::query()->where('code', 'WEEKDAY')->firstOrFail();

    $this->actingAs($employeeUser)
        ->post(route('employee-overtime.store'), [
            'overtime_type_id' => $type->getKey(),
            'work_date' => '2026-07-29',
            'start_time' => '18:00',
            'end_time' => '20:00',
            'break_minutes' => 0,
            'reason' => 'Penyediaan penutupan sistem bulanan',
            'work_description' => 'Menjalankan semakan integriti dan laporan akhir.',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    $overtime = OvertimeRequest::query()->firstOrFail();
    expect($overtime->status)->toBe('pending');
    expect($overtime->approval_stage)->toBe('supervisor');
    expect($overtime->requested_minutes)->toBe(120);
    expect($overtime->attendance_match_status)->toBe('matched');

    $this->actingAs($supervisor)
        ->patch(route('overtime-requests.supervisor-review', $overtime), [
            'status' => 'approved',
            'review_notes' => 'Disokong berdasarkan tugasan operasi.',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    expect($overtime->fresh()->approval_stage)->toBe('hr');

    $this->actingAs($hrAdmin)
        ->patch(route('overtime-requests.review', $overtime), [
            'status' => 'approved',
            'approved_minutes' => 90,
            'review_notes' => 'Diluluskan 90 minit.',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    expect($overtime->fresh()->status)->toBe('approved');
    expect($overtime->fresh()->approved_minutes)->toBe(90);
    $this->assertDatabaseHas('overtime_notifications', [
        'user_id' => $employeeUser->getKey(),
        'title' => 'Permohonan OT diluluskan',
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'overtime.approved',
        'auditable_id' => (string) $overtime->getKey(),
    ]);
});

test('overtime overlap and daily type limit are enforced on the server', function () {
    $employeeUser = User::factory()->employee()->create();
    createOvertimeEmployee($employeeUser);
    $type = OvertimeType::query()->where('code', 'WEEKDAY')->firstOrFail();
    $payload = [
        'overtime_type_id' => $type->getKey(),
        'work_date' => '2026-07-29',
        'start_time' => '18:00',
        'end_time' => '20:00',
        'break_minutes' => 0,
        'reason' => 'Tugasan operasi tambahan',
        'work_description' => 'Menyiapkan laporan operasi harian.',
    ];

    $this->actingAs($employeeUser)
        ->post(route('employee-overtime.store'), $payload)
        ->assertSessionDoesntHaveErrors();
    $this->actingAs($employeeUser)
        ->post(route('employee-overtime.store'), [
            ...$payload,
            'start_time' => '19:30',
            'end_time' => '21:00',
        ])
        ->assertSessionHasErrors('work_date');
    $this->actingAs($employeeUser)
        ->post(route('employee-overtime.store'), [
            ...$payload,
            'work_date' => '2026-07-30',
            'start_time' => '18:00',
            'end_time' => '23:00',
        ])
        ->assertSessionHasErrors('end_time');
});

test('overtime attachment is private and db_spp remains read only', function () {
    Storage::fake('local');
    $employeeUser = User::factory()->employee()->create();
    $otherEmployee = User::factory()->employee()->create();
    createOvertimeEmployee($employeeUser);
    $type = OvertimeType::query()->where('code', 'OTHER')->firstOrFail();
    $ibcoQueries = [];
    Event::listen(QueryExecuted::class, function (QueryExecuted $query) use (&$ibcoQueries) {
        if ($query->connectionName === 'ibco') {
            $ibcoQueries[] = strtolower(ltrim($query->sql));
        }
    });

    $this->actingAs($employeeUser)
        ->post(route('employee-overtime.store'), [
            'overtime_type_id' => $type->getKey(),
            'work_date' => '2026-07-29',
            'start_time' => '18:00',
            'end_time' => '19:00',
            'reason' => 'Tugasan khas pelanggan',
            'work_description' => 'Melaksanakan pemulihan data yang diluluskan.',
            'attachment' => UploadedFile::fake()->create(
                'arahan-kerja.pdf',
                100,
                'application/pdf',
            ),
        ])
        ->assertSessionDoesntHaveErrors();

    $overtime = OvertimeRequest::query()->firstOrFail();
    Storage::disk('local')->assertExists($overtime->attachment_path);
    $this->actingAs($employeeUser)
        ->get(route('employee-overtime.attachment', $overtime))
        ->assertOk();
    $this->actingAs($otherEmployee)
        ->get(route('employee-overtime.attachment', $overtime))
        ->assertForbidden();
    expect($ibcoQueries)->not->toBeEmpty();
    expect($ibcoQueries)->each->toStartWith('select');
    expect(AuditLog::query()->where('action', 'overtime.submitted')->exists())
        ->toBeTrue();
    expect(OvertimeNotification::query()->count())->toBe(0);
});

test('notification link can show pending overtime from every month', function () {
    $employeeUser = User::factory()->employee()->create();
    $hrAdmin = User::factory()->hrAdmin()->create();
    $employeeId = createOvertimeEmployee($employeeUser);
    $type = OvertimeType::query()->where('code', 'WEEKDAY')->firstOrFail();

    foreach (['2026-06-20', '2026-07-20'] as $workDate) {
        OvertimeRequest::query()->create([
            'user_id' => $employeeUser->getKey(),
            'employee_id' => $employeeId,
            'department_id' => 1,
            'overtime_type_id' => $type->getKey(),
            'work_date' => $workDate,
            'start_at' => "{$workDate} 18:00:00",
            'end_at' => "{$workDate} 19:00:00",
            'requested_minutes' => 60,
            'reason' => 'Tugasan operasi tertunggak',
            'work_description' => 'Menyiapkan tugasan operasi selepas waktu kerja.',
            'status' => 'pending',
            'approval_stage' => 'hr',
            'submitted_at' => "{$workDate} 17:00:00",
        ]);
    }

    $this->actingAs($hrAdmin)
        ->get(route('overtime-requests.index', [
            'status' => 'pending',
            'all_months' => 1,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('OvertimeRequests/Index')
            ->where('filters.all_months', true)
            ->where('requests.total', 2)
            ->has('requests.data', 2));
});
