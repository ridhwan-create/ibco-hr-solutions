<?php

use App\Models\EmployeeLeaveRequest;
use App\Models\EmployeeUserLink;
use App\Models\LeaveApprovalAssignment;
use App\Models\LeaveBalanceTransaction;
use App\Models\LeaveEntitlement;
use App\Models\LeaveType;
use App\Models\OfficeLocation;
use App\Models\PublicHoliday;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

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
        $table->string('email')->nullable();
        $table->boolean('rcd_enable')->default(true);
    });
    Schema::connection('ibco')->create('maklumatjawatan', function (Blueprint $table) {
        $table->increments('id');
        $table->integer('id_pekerja')->nullable();
        $table->integer('id_department')->nullable();
        $table->string('jumlahcuti')->nullable();
        $table->boolean('rcd_enable')->default(true);
    });
    Schema::connection('ibco')->create('xdepartment', function (Blueprint $table) {
        $table->increments('id');
        $table->string('description')->nullable();
        $table->boolean('rcd_enable')->default(true);
    });

    DB::connection('ibco')->table('xdepartment')->insert([
        ['id' => 1, 'description' => 'Teknologi Maklumat', 'rcd_enable' => 1],
        ['id' => 2, 'description' => 'Kewangan', 'rcd_enable' => 1],
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
    DB::disconnect('ibco');
});

function createCompleteLeaveEmployee(int $departmentId = 1): int
{
    $employeeId = DB::connection('ibco')->table('maklumatpekerja')->insertGetId([
        'employeeID' => 'EMP-LEAVE-'.$departmentId,
        'nama' => 'Pekerja Cuti '.$departmentId,
        'email' => "cuti{$departmentId}@ibco.test",
        'rcd_enable' => 1,
    ]);
    DB::connection('ibco')->table('maklumatjawatan')->insert([
        'id_pekerja' => $employeeId,
        'id_department' => $departmentId,
        'jumlahcuti' => '20',
        'rcd_enable' => 1,
    ]);

    return $employeeId;
}

function linkCompleteLeaveEmployee(User $user, int $employeeId): void
{
    $office = OfficeLocation::query()->firstOrCreate(
        ['name' => 'IBCO Solutions HQ'],
        [
            'address' => 'Kuala Lumpur',
            'latitude' => 3.139,
            'longitude' => 101.6869,
            'radius_meters' => 100,
            'accuracy_limit_meters' => 100,
            'is_active' => true,
        ],
    );
    EmployeeUserLink::query()->create([
        'user_id' => $user->getKey(),
        'employee_id' => $employeeId,
        'office_location_id' => $office->getKey(),
        'is_active' => true,
    ]);
}

test('leave follows supervisor then hr approval and balance is deducted and refunded', function () {
    $employeeUser = User::factory()->employee()->create();
    $supervisor = User::factory()->supervisor()->create();
    $hrAdmin = User::factory()->hrAdmin()->create();
    $employeeId = createCompleteLeaveEmployee();
    linkCompleteLeaveEmployee($employeeUser, $employeeId);
    LeaveApprovalAssignment::query()->create([
        'department_id' => 1,
        'approver_user_id' => $supervisor->getKey(),
        'is_active' => true,
    ]);
    $annual = LeaveType::query()->where('code', 'ANNUAL')->firstOrFail();
    LeaveEntitlement::query()->create([
        'employee_id' => $employeeId,
        'leave_type_id' => $annual->getKey(),
        'year' => 2026,
        'entitled_days' => 10,
    ]);

    $this->actingAs($employeeUser)
        ->post(route('employee-leave.store'), [
            'leave_type_id' => $annual->getKey(),
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-05',
            'reason' => 'Urusan keluarga penting',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    $leave = EmployeeLeaveRequest::query()->firstOrFail();
    expect($leave->status)->toBe('pending');
    expect($leave->approval_stage)->toBe('supervisor');
    expect((float) $leave->requested_days)->toBe(3.0);

    $this->actingAs($hrAdmin)
        ->patch(route('leave-requests.review', $leave), [
            'status' => 'approved',
            'review_notes' => 'Cuba lulus terlalu awal.',
        ])
        ->assertSessionHasErrors('status');

    $this->actingAs($supervisor)
        ->patch(route('leave-requests.supervisor-review', $leave), [
            'status' => 'approved',
            'review_notes' => 'Disokong penyelia.',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    expect($leave->fresh()->approval_stage)->toBe('hr');

    $this->actingAs($hrAdmin)
        ->patch(route('leave-requests.review', $leave), [
            'status' => 'approved',
            'review_notes' => 'Diluluskan HR.',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    expect($leave->fresh()->status)->toBe('approved');
    $this->assertDatabaseHas('leave_balance_transactions', [
        'leave_request_id' => $leave->getKey(),
        'transaction_type' => 'approval_deduction',
        'days' => -3,
    ]);
    expect((float) LeaveBalanceTransaction::query()->sum('days'))->toBe(-3.0);

    $this->actingAs($hrAdmin)
        ->patch(route('leave-requests.cancel-approved', $leave), [
            'cancellation_notes' => 'Program organisasi dibatalkan.',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    expect($leave->fresh()->status)->toBe('cancelled');
    expect((float) LeaveBalanceTransaction::query()->sum('days'))->toBe(0.0);
    $this->assertDatabaseHas('leave_notifications', [
        'user_id' => $employeeUser->getKey(),
        'title' => 'Cuti diluluskan dibatalkan',
    ]);
});

test('half day and public holiday rules are calculated on the server', function () {
    $employeeUser = User::factory()->employee()->create();
    $employeeId = createCompleteLeaveEmployee();
    linkCompleteLeaveEmployee($employeeUser, $employeeId);
    $annual = LeaveType::query()->where('code', 'ANNUAL')->firstOrFail();
    PublicHoliday::query()->create([
        'name' => 'Cuti Ujian',
        'holiday_date' => '2026-08-04',
        'is_active' => true,
    ]);

    $this->actingAs($employeeUser)
        ->post(route('employee-leave.store'), [
            'leave_type_id' => $annual->getKey(),
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-05',
            'duration_type' => 'full_day',
            'reason' => 'Permohonan tiga tarikh',
        ])
        ->assertSessionDoesntHaveErrors();

    expect((float) EmployeeLeaveRequest::query()->firstOrFail()->requested_days)
        ->toBe(2.0);

    EmployeeLeaveRequest::query()->delete();

    $this->actingAs($employeeUser)
        ->post(route('employee-leave.store'), [
            'leave_type_id' => $annual->getKey(),
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-03',
            'duration_type' => 'first_half',
            'reason' => 'Urusan separuh hari',
        ])
        ->assertSessionDoesntHaveErrors();

    expect((float) EmployeeLeaveRequest::query()->firstOrFail()->requested_days)
        ->toBe(0.5);
});

test('required leave attachment is private and downloadable only by authorised users', function () {
    Storage::fake('local');
    $employeeUser = User::factory()->employee()->create();
    $otherEmployee = User::factory()->employee()->create();
    $employeeId = createCompleteLeaveEmployee();
    linkCompleteLeaveEmployee($employeeUser, $employeeId);
    $sick = LeaveType::query()->where('code', 'SICK')->firstOrFail();

    $this->actingAs($employeeUser)
        ->post(route('employee-leave.store'), [
            'leave_type_id' => $sick->getKey(),
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-03',
            'reason' => 'Cuti sakit dengan sijil',
            'attachment' => UploadedFile::fake()->create(
                'sijil-sakit.pdf',
                100,
                'application/pdf',
            ),
        ])
        ->assertSessionDoesntHaveErrors();

    $leave = EmployeeLeaveRequest::query()->firstOrFail();
    Storage::disk('local')->assertExists($leave->attachment_path);
    $this->actingAs($employeeUser)
        ->get(route('employee-leave.attachment', $leave))
        ->assertOk();
    $this->actingAs($otherEmployee)
        ->get(route('employee-leave.attachment', $leave))
        ->assertForbidden();
});

test('unassigned supervisor cannot review another department request', function () {
    $employeeUser = User::factory()->employee()->create();
    $assignedSupervisor = User::factory()->supervisor()->create();
    $otherSupervisor = User::factory()->supervisor()->create();
    $employeeId = createCompleteLeaveEmployee();
    linkCompleteLeaveEmployee($employeeUser, $employeeId);
    LeaveApprovalAssignment::query()->create([
        'department_id' => 1,
        'approver_user_id' => $assignedSupervisor->getKey(),
        'is_active' => true,
    ]);
    $annual = LeaveType::query()->where('code', 'ANNUAL')->firstOrFail();
    $leave = EmployeeLeaveRequest::query()->create([
        'user_id' => $employeeUser->getKey(),
        'employee_id' => $employeeId,
        'department_id' => 1,
        'leave_type_id' => $annual->getKey(),
        'system_leave_type_id' => $annual->getKey(),
        'leave_type_label' => $annual->name,
        'start_date' => '2026-08-03',
        'end_date' => '2026-08-03',
        'requested_days' => 1,
        'reason' => 'Permohonan ujian',
        'status' => 'pending',
        'approval_stage' => 'supervisor',
        'submitted_at' => now(),
    ]);

    $this->actingAs($otherSupervisor)
        ->patch(route('leave-requests.supervisor-review', $leave), [
            'status' => 'approved',
            'review_notes' => 'Tidak sepatutnya dibenarkan.',
        ])
        ->assertForbidden();

    expect($leave->fresh()->approval_stage)->toBe('supervisor');
});

test('leave settings read db_spp using select queries only', function () {
    $hrAdmin = User::factory()->hrAdmin()->create();
    $supervisor = User::factory()->supervisor()->create();
    $employeeId = createCompleteLeaveEmployee();
    $annual = LeaveType::query()->where('code', 'ANNUAL')->firstOrFail();
    $legacyQueries = [];

    DB::listen(function (QueryExecuted $event) use (&$legacyQueries) {
        if ($event->connectionName === 'ibco') {
            $legacyQueries[] = strtolower(trim($event->sql));
        }
    });

    $this->actingAs($hrAdmin)
        ->post(route('leave-settings.entitlements.save'), [
            'employee_id' => $employeeId,
            'leave_type_id' => $annual->getKey(),
            'year' => 2026,
            'entitled_days' => 20,
            'carry_forward_days' => 2,
            'adjustment_days' => 0,
            'notes' => 'Kelayakan 2026',
        ])
        ->assertSessionDoesntHaveErrors();
    $this->actingAs($hrAdmin)
        ->post(route('leave-settings.assignments.save'), [
            'department_id' => 1,
            'approver_user_id' => $supervisor->getKey(),
            'is_active' => true,
        ])
        ->assertSessionDoesntHaveErrors();

    expect($legacyQueries)->not->toBeEmpty();
    expect($legacyQueries)->each->toStartWith('select');
    $this->assertDatabaseHas('leave_entitlements', [
        'employee_id' => $employeeId,
        'year' => 2026,
    ]);
    $this->assertDatabaseHas('leave_approval_assignments', [
        'department_id' => 1,
        'approver_user_id' => $supervisor->getKey(),
    ]);
});
