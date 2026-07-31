<?php

use App\Models\ClaimApprovalAssignment;
use App\Models\ClaimAttachment;
use App\Models\ClaimRequest;
use App\Models\ClaimType;
use App\Models\EmployeeSalaryProfile;
use App\Models\EmployeeUserLink;
use App\Models\OfficeLocation;
use App\Models\PayrollEntry;
use App\Models\PayrollEntryItem;
use App\Models\PayrollRun;
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

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-07-30 10:00:00');
    Storage::fake('local');

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
    Schema::connection('ibco')->create('xdepartment', function (Blueprint $table) {
        $table->increments('id');
        $table->string('description')->nullable();
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

function createClaimEmployee(User $user, int $departmentId = 1): int
{
    $employeeId = DB::connection('ibco')->table('maklumatpekerja')->insertGetId([
        'employeeID' => 'EMP-CLM-'.$user->getKey(),
        'nama' => 'Pekerja Tuntutan '.$user->getKey(),
        'tarikhlahir' => '1990-01-01',
        'kewarganegaraan' => 'Malaysia',
        'rcd_enable' => 1,
    ]);
    DB::connection('ibco')->table('maklumatjawatan')->insert([
        'id_pekerja' => $employeeId,
        'id_department' => $departmentId,
        'jawatan' => 'Eksekutif',
        'salary' => 3000,
        'rcd_enable' => 1,
    ]);
    $office = OfficeLocation::query()->create([
        'name' => 'IBCO Claims HQ '.$user->getKey(),
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

function claimPayload(ClaimType $type, string $receipt = 'RCPT-1001'): array
{
    return [
        'claim_type_id' => $type->getKey(),
        'expense_date' => '2026-07-25',
        'merchant_name' => 'Klinik IBCO',
        'receipt_number' => $receipt,
        'requested_amount' => 250,
        'description' => 'Rawatan pesakit luar yang layak dituntut.',
        'receipts' => [
            UploadedFile::fake()->create(
                'resit-klinik.pdf',
                100,
                'application/pdf',
            ),
        ],
    ];
}

test('claim follows supervisor and finance then enters payroll without statutory wages', function () {
    $employee = User::factory()->employee()->create();
    $supervisor = User::factory()->supervisor()->create();
    $hrAdmin = User::factory()->hrAdmin()->create();
    $approver = User::factory()->superAdmin()->create();
    $employeeId = createClaimEmployee($employee);
    $type = ClaimType::query()->where('code', 'MEDICAL')->firstOrFail();
    ClaimApprovalAssignment::query()->create([
        'department_id' => 1,
        'approver_user_id' => $supervisor->getKey(),
        'is_active' => true,
    ]);

    $this->actingAs($employee)
        ->post(route('employee-claims.store'), claimPayload($type))
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    $claim = ClaimRequest::query()->firstOrFail();
    expect($claim->approval_stage)->toBe('supervisor');
    expect((float) $claim->requested_amount)->toBe(250.0);
    expect($claim->attachments()->count())->toBe(1);

    $this->actingAs($supervisor)
        ->patch(route('claim-requests.supervisor-review', $claim), [
            'status' => 'approved',
            'review_notes' => 'Resit dan tujuan disahkan.',
        ])
        ->assertSessionDoesntHaveErrors();
    expect($claim->fresh()->approval_stage)->toBe('finance');

    $this->actingAs($hrAdmin)
        ->patch(route('claim-requests.review', $claim), [
            'status' => 'approved',
            'approved_amount' => 200,
            'scheduled_payroll_period' => '2026-07',
            'review_notes' => 'Diluluskan mengikut had kelayakan.',
        ])
        ->assertSessionDoesntHaveErrors();
    expect($claim->fresh()->status)->toBe('approved');
    expect((float) $claim->fresh()->approved_amount)->toBe(200.0);

    EmployeeSalaryProfile::query()->create([
        'employee_id' => $employeeId,
        'basic_salary' => 3000,
        'effective_from' => '2026-01-01',
        'is_active' => true,
        'updated_by' => $hrAdmin->getKey(),
    ]);
    $this->actingAs($hrAdmin)
        ->post(route('payroll.store'), ['period' => '2026-07'])
        ->assertSessionDoesntHaveErrors();

    $run = PayrollRun::query()->firstOrFail();
    $entry = PayrollEntry::query()->firstOrFail();
    $claimItem = PayrollEntryItem::query()
        ->where('category', 'claim_reimbursement')
        ->firstOrFail();
    expect((float) $entry->claim_reimbursements)->toBe(200.0);
    expect((float) $claimItem->amount)->toBe(200.0);
    expect($claimItem->is_epf_wage)->toBeFalse();
    expect($claimItem->is_socso_wage)->toBeFalse();
    expect($claimItem->is_eis_wage)->toBeFalse();
    expect($claimItem->is_pcb_wage)->toBeFalse();
    expect($claim->fresh()->payroll_run_id)->toBe($run->getKey());

    $this->actingAs($hrAdmin)
        ->patch(route('payroll.review', $run))
        ->assertSessionDoesntHaveErrors();
    $this->actingAs($approver)
        ->patch(route('payroll.approve', $run))
        ->assertSessionDoesntHaveErrors();
    $this->actingAs($approver)
        ->patch(route('payroll.finalize', $run))
        ->assertSessionDoesntHaveErrors();
    expect($claim->fresh()->paid_at)->not->toBeNull();
});

test('duplicate receipt and claim limits are enforced on the server', function () {
    $employee = User::factory()->employee()->create();
    createClaimEmployee($employee);
    $type = ClaimType::query()->where('code', 'MEDICAL')->firstOrFail();

    $this->actingAs($employee)
        ->post(route('employee-claims.store'), claimPayload($type))
        ->assertSessionDoesntHaveErrors();
    $this->actingAs($employee)
        ->post(route('employee-claims.store'), claimPayload($type))
        ->assertSessionHasErrors('receipt_number');
    $this->actingAs($employee)
        ->post(route('employee-claims.store'), [
            ...claimPayload($type, 'RCPT-OVER-LIMIT'),
            'requested_amount' => 700,
        ])
        ->assertSessionHasErrors('requested_amount');
    expect(ClaimRequest::query()->count())->toBe(1);
});

test('claim receipts remain private and legacy database is select only', function () {
    $employee = User::factory()->employee()->create();
    $otherEmployee = User::factory()->employee()->create();
    $hrAdmin = User::factory()->hrAdmin()->create();
    createClaimEmployee($employee);
    $type = ClaimType::query()->where('code', 'MEDICAL')->firstOrFail();
    $legacyQueries = [];
    Event::listen(QueryExecuted::class, function (QueryExecuted $query) use (&$legacyQueries) {
        if ($query->connectionName === 'ibco') {
            $legacyQueries[] = strtolower(ltrim($query->sql));
        }
    });

    $this->actingAs($employee)
        ->post(route('employee-claims.store'), claimPayload($type))
        ->assertSessionDoesntHaveErrors();
    $claim = ClaimRequest::query()->firstOrFail();
    $attachment = ClaimAttachment::query()->firstOrFail();
    Storage::disk('local')->assertExists($attachment->path);

    $this->actingAs($employee)
        ->get(route('employee-claims.attachment', [$claim, $attachment]))
        ->assertOk();
    $this->actingAs($otherEmployee)
        ->get(route('employee-claims.attachment', [$claim, $attachment]))
        ->assertForbidden();
    $this->actingAs($hrAdmin)
        ->get(route('claim-requests.attachment', [$claim, $attachment]))
        ->assertOk();

    expect($legacyQueries)->not->toBeEmpty();
    expect($legacyQueries)->each->toStartWith('select');
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'claim.submitted',
        'auditable_id' => (string) $claim->getKey(),
    ]);
});
