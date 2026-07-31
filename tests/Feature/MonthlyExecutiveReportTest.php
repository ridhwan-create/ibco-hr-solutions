<?php

use App\Models\EmployeeLeaveRequest;
use App\Models\EmployeeUserLink;
use App\Models\GeoAttendanceRecord;
use App\Models\LeaveType;
use App\Models\OfficeLocation;
use App\Models\OvertimeRequest;
use App\Models\OvertimeType;
use App\Models\PayrollEntry;
use App\Models\PayrollRun;
use App\Models\PayrollStatutorySnapshot;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-07-30 10:00:00');

    config()->set('database.connections.ibco', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
    DB::purge('ibco');

    Schema::connection('ibco')->create('maklumatpekerja', function (
        Blueprint $table,
    ) {
        $table->increments('id');
        $table->string('employeeID', 30)->nullable();
        $table->string('nama')->nullable();
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
    Schema::connection('ibco')->create('xdepartment', function (
        Blueprint $table,
    ) {
        $table->increments('id');
        $table->string('description')->nullable();
        $table->boolean('rcd_enable')->default(true);
    });
    Schema::connection('ibco')->create('reportbulanan', function (
        Blueprint $table,
    ) {
        $table->increments('id');
        $table->integer('id_pekerja')->nullable();
        $table->date('date_mula');
        $table->date('date_akhir');
        $table->text('laporan')->nullable();
        $table->boolean('rcd_enable')->default(true);
    });
    Schema::connection('ibco')->create('maklumatcuti', function (
        Blueprint $table,
    ) {
        $table->increments('id');
        $table->integer('id_pekerja')->nullable();
        $table->integer('jenis_cuti')->nullable();
        $table->integer('status_permohonan')->nullable();
        $table->date('date_mulacuti')->nullable();
        $table->date('date_tamatcuti')->nullable();
        $table->boolean('rcd_enable')->default(true);
    });
    Schema::connection('ibco')->create('xsenaraicuti', function (
        Blueprint $table,
    ) {
        $table->increments('id');
        $table->string('description')->nullable();
    });
    Schema::connection('ibco')->create('xstatuscuti', function (
        Blueprint $table,
    ) {
        $table->increments('id');
        $table->string('description')->nullable();
    });
    Schema::connection('ibco')->create('maklumatot', function (
        Blueprint $table,
    ) {
        $table->increments('id');
        $table->integer('id_pekerja')->nullable();
        $table->boolean('rcd_enable')->default(true);
    });

    DB::connection('ibco')->table('xdepartment')->insert([
        'id' => 1,
        'description' => 'Teknologi Maklumat',
        'rcd_enable' => 1,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
    DB::disconnect('ibco');
});

function createMonthlyReportEmployee(User $user): int
{
    $employeeId = DB::connection('ibco')->table('maklumatpekerja')->insertGetId([
        'employeeID' => 'EMP-RPT-'.$user->getKey(),
        'nama' => 'Pekerja Laporan',
        'rcd_enable' => 1,
    ]);
    DB::connection('ibco')->table('maklumatjawatan')->insert([
        'id_pekerja' => $employeeId,
        'id_department' => 1,
        'rcd_enable' => 1,
    ]);
    $office = OfficeLocation::query()->create([
        'name' => 'IBCO Solutions HQ',
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

function seedMonthlyReportOperations(
    int $employeeId,
    User $employeeUser,
    User $hrAdmin,
): PayrollRun {
    $officeId = EmployeeUserLink::query()
        ->where('user_id', $employeeUser->getKey())
        ->value('office_location_id');
    GeoAttendanceRecord::query()->create([
        'user_id' => $employeeUser->getKey(),
        'employee_id' => $employeeId,
        'office_location_id' => $officeId,
        'attendance_date' => '2026-07-14',
        'clock_in_at' => '2026-07-14 09:00:00',
        'clock_out_at' => '2026-07-14 18:00:00',
        'source' => 'geolocation',
        'status' => 'active',
    ]);
    $leaveType = LeaveType::query()->where('code', 'ANNUAL')->firstOrFail();
    EmployeeLeaveRequest::query()->create([
        'user_id' => $employeeUser->getKey(),
        'employee_id' => $employeeId,
        'department_id' => 1,
        'leave_type_id' => $leaveType->getKey(),
        'system_leave_type_id' => $leaveType->getKey(),
        'leave_type_label' => $leaveType->name,
        'start_date' => '2026-07-15',
        'end_date' => '2026-07-15',
        'duration_type' => 'full_day',
        'requested_days' => 1,
        'reason' => 'Urusan keluarga',
        'status' => 'approved',
        'approval_stage' => 'completed',
        'submitted_at' => now(),
        'reviewed_at' => now(),
        'reviewed_by' => $hrAdmin->getKey(),
    ]);
    $overtimeType = OvertimeType::query()
        ->where('code', 'WEEKDAY')
        ->firstOrFail();
    OvertimeRequest::query()->create([
        'user_id' => $employeeUser->getKey(),
        'employee_id' => $employeeId,
        'department_id' => 1,
        'overtime_type_id' => $overtimeType->getKey(),
        'work_date' => '2026-07-16',
        'start_at' => '2026-07-16 18:00:00',
        'end_at' => '2026-07-16 20:00:00',
        'break_minutes' => 0,
        'requested_minutes' => 120,
        'approved_minutes' => 120,
        'attendance_match_status' => 'matched',
        'reason' => 'Penutupan sistem',
        'work_description' => 'Menyiapkan laporan operasi.',
        'status' => 'approved',
        'approval_stage' => 'completed',
        'submitted_at' => now(),
        'reviewed_at' => now(),
        'reviewed_by' => $hrAdmin->getKey(),
    ]);
    $run = PayrollRun::query()->create([
        'period_start' => '2026-07-01',
        'status' => 'finalized',
        'currency' => 'MYR',
        'employee_count' => 1,
        'total_basic_salary' => 3000,
        'total_earnings' => 3200,
        'total_deductions' => 450,
        'total_net_pay' => 2750,
        'total_employee_statutory' => 400,
        'total_employer_statutory' => 500,
        'total_pcb' => 50,
        'generated_at' => now(),
        'generated_by' => $hrAdmin->getKey(),
        'finalized_at' => now(),
        'finalized_by' => $hrAdmin->getKey(),
    ]);
    $entry = PayrollEntry::query()->create([
        'payroll_run_id' => $run->getKey(),
        'employee_id' => $employeeId,
        'employee_number' => 'EMP-RPT-'.$employeeUser->getKey(),
        'employee_name' => 'Pekerja Laporan',
        'basic_salary' => 3000,
        'overtime_minutes' => 120,
        'overtime_amount' => 100,
        'unpaid_leave_days' => 0,
        'unpaid_leave_amount' => 0,
        'recurring_earnings' => 100,
        'recurring_deductions' => 0,
        'manual_earnings' => 0,
        'manual_deductions' => 0,
        'gross_pay' => 3200,
        'total_deductions' => 450,
        'net_pay' => 2750,
        'calculated_at' => now(),
    ]);
    PayrollStatutorySnapshot::query()->create([
        'payroll_entry_id' => $entry->getKey(),
        'kwsp_category' => 'citizen_below_60',
        'socso_category' => 'first',
        'eis_enabled' => true,
        'total_employee_deductions' => 400,
        'total_employer_contributions' => 500,
        'rate_version' => 'Ujian Julai 2026',
        'calculated_at' => now(),
    ]);

    return $run;
}

test('monthly executive report combines operations and exports csv using db_spp as select only', function () {
    $hrAdmin = User::factory()->hrAdmin()->create();
    $employeeUser = User::factory()->employee()->create();
    $employeeId = createMonthlyReportEmployee($employeeUser);
    seedMonthlyReportOperations($employeeId, $employeeUser, $hrAdmin);
    $ibcoQueries = [];
    Event::listen(QueryExecuted::class, function (QueryExecuted $query) use (
        &$ibcoQueries,
    ) {
        if ($query->connectionName === 'ibco') {
            $ibcoQueries[] = strtolower(ltrim($query->sql));
        }
    });

    $this->actingAs($hrAdmin)
        ->get(route('laporan-bulanan.index', ['period' => '2026-07']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('MonthlyReports/Index')
            ->where('report.period', '2026-07')
            ->where('report.summary.active_employees', 1)
            ->where('report.summary.attendance_days', 1)
            ->where('report.summary.leave_days', 1)
            ->where('report.summary.overtime_hours', 2)
            ->where('report.summary.payroll.status', 'finalized')
            ->where('report.summary.payroll.net_pay', 2750)
            ->has('report.departments', 1)
            ->has('report.trend', 6));

    $this->actingAs($hrAdmin)
        ->get(route('laporan-bulanan.export', ['period' => '2026-07']))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    expect($ibcoQueries)->not->toBeEmpty();
    expect($ibcoQueries)->each->toStartWith('select');
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'report.monthly_exported',
        'auditable_id' => '2026-07',
    ]);
});

test('dashboard exposes executive overview only to report roles', function () {
    $hrAdmin = User::factory()->hrAdmin()->create();
    $employeeUser = User::factory()->employee()->create();
    $employeeId = createMonthlyReportEmployee($employeeUser);
    seedMonthlyReportOperations($employeeId, $employeeUser, $hrAdmin);

    $this->actingAs($hrAdmin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('executiveOverview.period', '2026-07')
            ->where(
                'executiveOverview.summary.payroll.status',
                'finalized',
            ));

    $viewer = User::factory()->create();
    $this->actingAs($viewer)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('executiveOverview', null));
});

test('legacy monthly reports remain read only and unauthorized roles cannot access executive report', function () {
    $hrAdmin = User::factory()->hrAdmin()->create();
    $employeeUser = User::factory()->employee()->create();
    $employeeId = createMonthlyReportEmployee($employeeUser);
    DB::connection('ibco')->table('reportbulanan')->insert([
        'id_pekerja' => $employeeId,
        'date_mula' => '2026-07-01',
        'date_akhir' => '2026-07-31',
        'laporan' => 'Laporan operasi lama',
        'rcd_enable' => 1,
    ]);

    $this->actingAs($hrAdmin)
        ->get(route('laporan-bulanan.legacy'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ReportBulanan/Index')
            ->has('records.data', 1));

    $viewer = User::factory()->create();
    $this->actingAs($viewer)
        ->get(route('laporan-bulanan.index'))
        ->assertForbidden();
    $this->actingAs($viewer)
        ->get(route('laporan-bulanan.export'))
        ->assertForbidden();
});
