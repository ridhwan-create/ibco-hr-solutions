<?php

use App\Models\EmployeeSalaryProfile;
use App\Models\EmployeeStatutoryProfile;
use App\Models\EmployeeUserLink;
use App\Models\OfficeLocation;
use App\Models\PayrollEntry;
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

function createStatutoryEmployee(string $number = 'EMP-STAT-001'): int
{
    $employeeId = DB::connection('ibco')->table('maklumatpekerja')->insertGetId([
        'employeeID' => $number,
        'nama' => 'Pekerja Statutori',
        'tarikhlahir' => '1990-01-01',
        'kewarganegaraan' => 'Malaysia',
        'rcd_enable' => 1,
    ]);
    DB::connection('ibco')->table('maklumatjawatan')->insert([
        'id_pekerja' => $employeeId,
        'id_department' => 1,
        'jawatan' => 'Eksekutif',
        'salary' => 3000,
        'noepf' => 'EPF-STAT-001',
        'nosocso' => 'SOCSO-STAT-001',
        'rcd_enable' => 1,
    ]);

    return $employeeId;
}

test('statutory contributions are snapshotted and employee can download only own finalized payslip', function () {
    $hr = User::factory()->hrAdmin()->create();
    $approver = User::factory()->superAdmin()->create();
    $employeeUser = User::factory()->employee()->create();
    $otherEmployee = User::factory()->employee()->create();
    $employeeId = createStatutoryEmployee();
    $office = OfficeLocation::query()->create([
        'name' => 'IBCO Solutions HQ',
        'address' => 'Kuala Lumpur',
        'latitude' => 3.139,
        'longitude' => 101.6869,
        'radius_meters' => 100,
        'accuracy_limit_meters' => 100,
        'is_active' => true,
        'created_by' => $hr->getKey(),
        'updated_by' => $hr->getKey(),
    ]);
    EmployeeUserLink::query()->create([
        'user_id' => $employeeUser->getKey(),
        'employee_id' => $employeeId,
        'office_location_id' => $office->getKey(),
        'is_active' => true,
        'created_by' => $hr->getKey(),
        'updated_by' => $hr->getKey(),
    ]);
    EmployeeSalaryProfile::query()->create([
        'employee_id' => $employeeId,
        'basic_salary' => 3000,
        'effective_from' => '2026-01-01',
        'is_active' => true,
        'updated_by' => $hr->getKey(),
    ]);
    EmployeeStatutoryProfile::query()->create([
        'employee_id' => $employeeId,
        'kwsp_category' => 'citizen_below_60',
        'socso_category' => 'first',
        'eis_enabled' => true,
        'pcb_method' => 'fixed',
        'pcb_monthly_amount' => 25,
        'epf_number' => 'EPF-STAT-001',
        'socso_number' => 'SOCSO-STAT-001',
        'tax_number' => 'TAX-STAT-001',
        'effective_from' => '2026-01-01',
        'is_active' => true,
        'updated_by' => $hr->getKey(),
    ]);

    $this->actingAs($hr)
        ->post(route('payroll.store'), ['period' => '2026-07'])
        ->assertSessionDoesntHaveErrors();

    $run = PayrollRun::query()->firstOrFail();
    $entry = PayrollEntry::query()->with('statutorySnapshot')->firstOrFail();
    expect((float) $entry->statutorySnapshot->kwsp_employee)->toBe(330.0);
    expect((float) $entry->statutorySnapshot->kwsp_employer)->toBe(390.0);
    expect((float) $entry->statutorySnapshot->socso_employee)->toBe(36.9);
    expect((float) $entry->statutorySnapshot->socso_employer)->toBe(51.65);
    expect((float) $entry->statutorySnapshot->eis_employee)->toBe(5.9);
    expect((float) $entry->statutorySnapshot->pcb)->toBe(25.0);
    expect((float) $entry->net_pay)->toBe(2602.2);

    $this->actingAs($employeeUser)
        ->get(route('payslips.download', $entry))
        ->assertNotFound();

    $this->actingAs($hr)
        ->patch(route('payroll.review', $run))
        ->assertSessionDoesntHaveErrors();
    $this->actingAs($approver)
        ->patch(route('payroll.approve', $run))
        ->assertSessionDoesntHaveErrors();
    $this->actingAs($approver)
        ->patch(route('payroll.finalize', $run))
        ->assertSessionDoesntHaveErrors();

    $this->actingAs($employeeUser)
        ->get(route('payslips.index'))
        ->assertOk();
    $response = $this->actingAs($employeeUser)
        ->get(route('payslips.download', $entry))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
    expect($response->getContent())->toStartWith('%PDF-1.4');
    $this->actingAs($otherEmployee)
        ->get(route('payslips.download', $entry))
        ->assertNotFound();
});

test('hr may override draft statutory values and db_spp remains select only', function () {
    $hr = User::factory()->hrAdmin()->create();
    $employeeId = createStatutoryEmployee('EMP-STAT-002');
    EmployeeSalaryProfile::query()->create([
        'employee_id' => $employeeId,
        'basic_salary' => 3000,
        'effective_from' => '2026-01-01',
        'is_active' => true,
        'updated_by' => $hr->getKey(),
    ]);
    $ibcoQueries = [];
    Event::listen(QueryExecuted::class, function (QueryExecuted $query) use (&$ibcoQueries) {
        if ($query->connectionName === 'ibco') {
            $ibcoQueries[] = strtolower(ltrim($query->sql));
        }
    });

    $this->actingAs($hr)
        ->post(route('payroll.store'), ['period' => '2026-07'])
        ->assertSessionDoesntHaveErrors();
    $run = PayrollRun::query()->firstOrFail();
    $entry = PayrollEntry::query()->firstOrFail();

    $this->actingAs($hr)
        ->put(route('payroll.statutory.update', [$run, $entry]), [
            'kwsp_employee' => 300,
            'kwsp_employer' => 360,
            'socso_employee' => 35,
            'socso_employer' => 50,
            'eis_employee' => 6,
            'eis_employer' => 6,
            'pcb' => 40,
            'notes' => 'Disahkan melalui kalkulator rasmi.',
        ])
        ->assertSessionDoesntHaveErrors();

    $entry->load('statutorySnapshot');
    expect($entry->statutorySnapshot->is_overridden)->toBeTrue();
    expect((float) $entry->statutorySnapshot->pcb)->toBe(40.0);
    expect((float) $entry->fresh()->net_pay)->toBe(2619.0);
    expect($ibcoQueries)->not->toBeEmpty();
    expect($ibcoQueries)->each->toStartWith('select');
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'payroll.statutory_overridden',
    ]);
});
