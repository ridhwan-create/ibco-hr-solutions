<?php

use App\Models\EmployeeLeaveRequest;
use App\Models\EmployeePayrollComponent;
use App\Models\EmployeeSalaryProfile;
use App\Models\LeaveType;
use App\Models\OvertimeRequest;
use App\Models\OvertimeType;
use App\Models\PayrollComponent;
use App\Models\PayrollEntry;
use App\Models\PayrollEntryItem;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-07-29 12:00:00');

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
        $table->date('tarikhlahir')->nullable();
        $table->string('kewarganegaraan')->nullable();
        $table->boolean('rcd_enable')->default(true);
    });
    Schema::connection('ibco')->create('maklumatjawatan', function (Blueprint $table) {
        $table->increments('id');
        $table->integer('id_pekerja')->nullable();
        $table->integer('id_department')->nullable();
        $table->string('jawatan')->nullable();
        $table->decimal('salary', 12, 2)->nullable();
        $table->string('noepf')->nullable();
        $table->string('nosocso')->nullable();
        $table->boolean('rcd_enable')->default(true);
    });
});

afterEach(function () {
    Carbon::setTestNow();
    DB::disconnect('ibco');
});

function createPayrollEmployee(
    string $number = 'EMP-PAY-001',
    string $name = 'Pekerja Payroll',
): int {
    $employeeId = DB::connection('ibco')->table('maklumatpekerja')->insertGetId([
        'employeeID' => $number,
        'nama' => $name,
        'tarikhlahir' => '1990-01-01',
        'kewarganegaraan' => 'Malaysia',
        'rcd_enable' => 1,
    ]);
    DB::connection('ibco')->table('maklumatjawatan')->insert([
        'id_pekerja' => $employeeId,
        'id_department' => 1,
        'jawatan' => 'Eksekutif',
        'salary' => 2500,
        'rcd_enable' => 1,
    ]);

    return $employeeId;
}

function configurePayrollEmployee(int $employeeId, User $actor): void
{
    EmployeeSalaryProfile::query()->create([
        'employee_id' => $employeeId,
        'basic_salary' => 2600,
        'effective_from' => '2026-01-01',
        'is_active' => true,
        'updated_by' => $actor->getKey(),
    ]);
    $allowance = PayrollComponent::query()
        ->where('code', 'FIXED_ALLOWANCE')
        ->firstOrFail();
    $deduction = PayrollComponent::query()
        ->where('code', 'FIXED_DEDUCTION')
        ->firstOrFail();

    EmployeePayrollComponent::query()->create([
        'employee_id' => $employeeId,
        'payroll_component_id' => $allowance->getKey(),
        'amount' => 200,
        'effective_from' => '2026-01-01',
        'is_active' => true,
        'updated_by' => $actor->getKey(),
    ]);
    EmployeePayrollComponent::query()->create([
        'employee_id' => $employeeId,
        'payroll_component_id' => $deduction->getKey(),
        'amount' => 50,
        'effective_from' => '2026-01-01',
        'is_active' => true,
        'updated_by' => $actor->getKey(),
    ]);
}

test('payroll core calculates approved ot and unpaid leave then follows approval lock', function () {
    $hrAdmin = User::factory()->hrAdmin()->create();
    $approver = User::factory()->hrManager()->create();
    $employeeUser = User::factory()->employee()->create();
    $employeeId = createPayrollEmployee();
    configurePayrollEmployee($employeeId, $hrAdmin);
    $overtimeType = OvertimeType::query()
        ->where('code', 'WEEKDAY')
        ->firstOrFail();
    $unpaidType = LeaveType::query()->where('code', 'UNPAID')->firstOrFail();

    OvertimeRequest::query()->create([
        'user_id' => $employeeUser->getKey(),
        'employee_id' => $employeeId,
        'overtime_type_id' => $overtimeType->getKey(),
        'work_date' => '2026-07-14',
        'start_at' => '2026-07-14 18:00:00',
        'end_at' => '2026-07-14 20:00:00',
        'break_minutes' => 0,
        'requested_minutes' => 120,
        'approved_minutes' => 120,
        'attendance_match_status' => 'matched',
        'reason' => 'Penutupan sistem',
        'work_description' => 'Menjalankan penutupan sistem bulanan.',
        'status' => 'approved',
        'approval_stage' => 'completed',
        'submitted_at' => now(),
        'reviewed_at' => now(),
        'reviewed_by' => $hrAdmin->getKey(),
    ]);
    EmployeeLeaveRequest::query()->create([
        'user_id' => $employeeUser->getKey(),
        'employee_id' => $employeeId,
        'leave_type_id' => $unpaidType->getKey(),
        'system_leave_type_id' => $unpaidType->getKey(),
        'leave_type_label' => $unpaidType->name,
        'start_date' => '2026-07-15',
        'end_date' => '2026-07-15',
        'duration_type' => 'full_day',
        'requested_days' => 1,
        'reason' => 'Urusan peribadi',
        'status' => 'approved',
        'approval_stage' => 'completed',
        'submitted_at' => now(),
        'reviewed_at' => now(),
        'reviewed_by' => $hrAdmin->getKey(),
    ]);

    $this->actingAs($hrAdmin)
        ->post(route('payroll.store'), ['period' => '2026-07'])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    $run = PayrollRun::query()->firstOrFail();
    $entry = PayrollEntry::query()->firstOrFail();
    expect($run->status)->toBe('draft');
    expect($run->employee_count)->toBe(1);
    expect((float) $entry->basic_salary)->toBe(2600.0);
    expect($entry->overtime_minutes)->toBe(120);
    expect((float) $entry->overtime_amount)->toBe(37.49);
    expect((float) $entry->unpaid_leave_days)->toBe(1.0);
    expect((float) $entry->unpaid_leave_amount)->toBe(100.0);
    expect((float) $entry->gross_pay)->toBe(2837.49);
    expect((float) $entry->total_deductions)->toBe(486.9);
    expect((float) $entry->net_pay)->toBe(2350.59);
    expect((float) $entry->statutorySnapshot->kwsp_employee)->toBe(297.0);
    expect((float) $entry->statutorySnapshot->socso_employee)->toBe(34.4);
    expect((float) $entry->statutorySnapshot->eis_employee)->toBe(5.5);
    expect((float) $entry->statutorySnapshot->total_employer_contributions)
        ->toBe(404.65);

    $this->actingAs($hrAdmin)
        ->patch(route('payroll.review', $run), [
            'notes' => 'Semakan HR lengkap.',
        ])
        ->assertSessionDoesntHaveErrors();
    expect($run->fresh()->status)->toBe('hr_reviewed');

    $this->actingAs($hrAdmin)
        ->patch(route('payroll.approve', $run))
        ->assertForbidden();

    $this->actingAs($approver)
        ->patch(route('payroll.approve', $run), [
            'notes' => 'Jumlah disahkan.',
        ])
        ->assertSessionDoesntHaveErrors();
    $this->actingAs($approver)
        ->patch(route('payroll.finalize', $run))
        ->assertSessionDoesntHaveErrors();
    expect($run->fresh()->status)->toBe('finalized');

    $this->actingAs($hrAdmin)
        ->post(route('payroll.recalculate', $run))
        ->assertSessionHasErrors('payroll');
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'payroll.finalized',
        'auditable_id' => (string) $run->getKey(),
    ]);
});

test('manual payroll adjustments update entry and run totals only while draft', function () {
    $hrAdmin = User::factory()->hrAdmin()->create();
    $employeeId = createPayrollEmployee();
    configurePayrollEmployee($employeeId, $hrAdmin);

    $this->actingAs($hrAdmin)
        ->post(route('payroll.store'), ['period' => '2026-07'])
        ->assertSessionDoesntHaveErrors();

    $run = PayrollRun::query()->firstOrFail();
    $entry = PayrollEntry::query()->firstOrFail();
    $startingNet = (float) $entry->net_pay;

    $this->actingAs($hrAdmin)
        ->post(route('payroll.items.store', [$run, $entry]), [
            'name' => 'Bonus Projek',
            'type' => 'earning',
            'amount' => 100,
            'notes' => 'Pelarasan bulan Julai',
        ])
        ->assertSessionDoesntHaveErrors();

    $item = PayrollEntryItem::query()
        ->where('is_manual', true)
        ->firstOrFail();
    expect((float) $entry->fresh()->net_pay)->toBe($startingNet + 100);
    expect((float) $run->fresh()->total_net_pay)->toBe($startingNet + 100);

    $this->actingAs($hrAdmin)
        ->delete(route('payroll.items.destroy', [$run, $entry, $item]))
        ->assertSessionDoesntHaveErrors();
    expect((float) $entry->fresh()->net_pay)->toBe($startingNet);
    expect(PayrollEntryItem::query()->whereKey($item->getKey())->exists())
        ->toBeFalse();
});

test('payroll settings and generation use db_spp as select only', function () {
    $hrAdmin = User::factory()->hrAdmin()->create();
    $employeeId = createPayrollEmployee();
    $ibcoQueries = [];
    Event::listen(QueryExecuted::class, function (QueryExecuted $query) use (&$ibcoQueries) {
        if ($query->connectionName === 'ibco') {
            $ibcoQueries[] = strtolower(ltrim($query->sql));
        }
    });

    $this->actingAs($hrAdmin)
        ->get(route('payroll-settings.index'))
        ->assertOk();
    $this->actingAs($hrAdmin)
        ->post(route('payroll-settings.salary-profiles.save'), [
            'employee_id' => $employeeId,
            'basic_salary' => 2600,
            'effective_from' => '2026-01-01',
            'is_active' => true,
        ])
        ->assertSessionDoesntHaveErrors();
    $this->actingAs($hrAdmin)
        ->post(route('payroll.store'), ['period' => '2026-07'])
        ->assertSessionDoesntHaveErrors();

    expect($ibcoQueries)->not->toBeEmpty();
    expect($ibcoQueries)->each->toStartWith('select');
});

test('viewer and employee cannot see confidential payroll core', function () {
    $viewer = User::factory()->create();
    $employee = User::factory()->employee()->create();

    $this->actingAs($viewer)
        ->get(route('payroll.index'))
        ->assertForbidden();
    $this->actingAs($employee)
        ->get(route('payroll.index'))
        ->assertForbidden();
    $this->actingAs($employee)
        ->get(route('payroll-settings.index'))
        ->assertForbidden();
});
