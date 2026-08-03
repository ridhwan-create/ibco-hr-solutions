<?php

use App\Models\AuditLog;
use App\Models\Competency;
use App\Models\CompetencyRequirement;
use App\Models\DevelopmentPlan;
use App\Models\EmployeeCompetency;
use App\Models\EmployeeUserLink;
use App\Models\OfficeLocation;
use App\Models\TrainingApprovalAssignment;
use App\Models\TrainingBudget;
use App\Models\TrainingCourse;
use App\Models\TrainingProvider;
use App\Models\TrainingRequest;
use App\Models\TrainingSession;
use App\Models\User;
use App\Support\LeaveApprovalAlerts;
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
    Carbon::setTestNow('2026-08-01 10:00:00');
    Storage::fake('local');
    config()->set('database.connections.ibco', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
    DB::purge('ibco');

    Schema::connection('ibco')->create('xdepartment', function (Blueprint $table) {
        $table->increments('id');
        $table->string('description')->nullable();
        $table->boolean('rcd_enable')->default(true);
    });
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
        $table->string('jawatan')->nullable();
        $table->boolean('rcd_enable')->default(true);
    });
    DB::connection('ibco')->table('xdepartment')->insert([
        'id' => 1,
        'description' => 'Teknologi Maklumat',
        'rcd_enable' => 1,
    ]);
    DB::connection('ibco')->table('maklumatpekerja')->insert([
        'id' => 100,
        'employeeID' => 'EMP-100',
        'nama' => 'Nur Employee',
        'rcd_enable' => 1,
    ]);
    DB::connection('ibco')->table('maklumatjawatan')->insert([
        'id' => 1000,
        'id_pekerja' => 100,
        'id_department' => 1,
        'jawatan' => 'Eksekutif Teknologi Maklumat',
        'rcd_enable' => 1,
    ]);
    OfficeLocation::query()->create([
        'name' => 'IBCO Solutions HQ',
        'address' => 'Kuala Lumpur',
        'latitude' => 3.1390000,
        'longitude' => 101.6869000,
        'radius_meters' => 100,
        'accuracy_limit_meters' => 100,
        'is_active' => true,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
    DB::disconnect('ibco');
});

test('complete training competency and development workflow keeps db_spp read only', function () {
    $employee = User::factory()->employee()->create(['name' => 'Nur Employee']);
    $supervisor = User::factory()->supervisor()->create();
    $hr = User::factory()->hrAdmin()->create();
    $hrManager = User::factory()->hrManager()->create();
    EmployeeUserLink::query()->create([
        'user_id' => $employee->getKey(),
        'employee_id' => 100,
        'office_location_id' => OfficeLocation::query()->value('id'),
        'is_active' => true,
        'created_by' => $hr->getKey(),
        'updated_by' => $hr->getKey(),
    ]);
    TrainingApprovalAssignment::query()->create([
        'department_id' => 1,
        'approver_user_id' => $supervisor->getKey(),
        'is_active' => true,
        'created_by' => $hr->getKey(),
    ]);
    TrainingBudget::query()->create([
        'year' => 2026,
        'department_id' => 1,
        'budget_code' => 'LND-IT-2026',
        'allocated_amount' => 1000,
        'created_by' => $hr->getKey(),
        'updated_by' => $hr->getKey(),
    ]);
    $provider = TrainingProvider::query()->create([
        'code' => 'IBCO-ACADEMY',
        'name' => 'IBCO Academy',
        'is_active' => true,
        'created_by' => $hr->getKey(),
        'updated_by' => $hr->getKey(),
    ]);
    $course = TrainingCourse::query()->create([
        'training_provider_id' => $provider->getKey(),
        'code' => 'SEC-101',
        'title' => 'Laravel Security Fundamentals',
        'category' => 'technical',
        'delivery_method' => 'physical',
        'duration_hours' => 8,
        'cpd_points' => 8,
        'default_cost' => 300,
        'currency' => 'MYR',
        'certificate_validity_months' => 24,
        'is_mandatory' => false,
        'is_active' => true,
        'created_by' => $hr->getKey(),
        'updated_by' => $hr->getKey(),
    ]);
    $session = TrainingSession::query()->create([
        'training_course_id' => $course->getKey(),
        'session_code' => 'SEC-101-AUG26',
        'starts_at' => '2026-08-20 09:00:00',
        'ends_at' => '2026-08-20 17:00:00',
        'registration_deadline' => '2026-08-15',
        'venue' => 'Bilik Latihan IBCO',
        'facilitator' => 'Trainer IBCO',
        'capacity' => 20,
        'cost_per_participant' => 300,
        'budget_code' => 'LND-IT-2026',
        'status' => 'open',
        'created_by' => $hr->getKey(),
        'updated_by' => $hr->getKey(),
    ]);
    $competency = Competency::query()->create([
        'code' => 'APP-SEC',
        'name' => 'Application Security',
        'category' => 'technical',
        'maximum_level' => 5,
        'is_active' => true,
        'created_by' => $hr->getKey(),
        'updated_by' => $hr->getKey(),
    ]);
    CompetencyRequirement::query()->create([
        'competency_id' => $competency->getKey(),
        'department_id' => 1,
        'position_name' => 'Eksekutif Teknologi Maklumat',
        'required_level' => 3,
        'is_mandatory' => true,
        'created_by' => $hr->getKey(),
    ]);

    $legacyQueries = [];
    Event::listen(QueryExecuted::class, function (QueryExecuted $event) use (&$legacyQueries) {
        if ($event->connectionName === 'ibco') {
            $legacyQueries[] = strtolower(ltrim($event->sql));
        }
    });

    $this->actingAs($hr)
        ->post(route('training.development-plans.store'), [
            'employee_user_id' => $employee->getKey(),
            'competency_id' => $competency->getKey(),
            'source' => 'competency_gap',
            'title' => 'Tingkatkan Application Security ke Tahap 3',
            'action_plan' => 'Hadir latihan dan lulus penilaian akhir.',
            'target_level' => 3,
            'due_date' => '2026-09-30',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    $plan = DevelopmentPlan::query()->firstOrFail();

    $this->actingAs($employee)
        ->post(route('employee-training.store'), [
            'training_session_id' => $session->getKey(),
            'justification' => 'Menutup jurang kompetensi keselamatan aplikasi.',
            'development_source' => 'competency_gap',
            'development_plan_id' => $plan->getKey(),
            'estimated_cost' => 300,
            'supporting_document' => UploadedFile::fake()->create(
                'pelan-pembangunan.pdf',
                100,
                'application/pdf',
            ),
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    $training = TrainingRequest::query()->with('attachments')->firstOrFail();
    expect($training->status)->toBe('pending')
        ->and($training->approval_stage)->toBe('supervisor')
        ->and($training->budget_year)->toBe(2026)
        ->and($training->attachments)->toHaveCount(1);
    expect(app(LeaveApprovalAlerts::class)->summarizeFor($supervisor)['training_supervisor'])->toBe(1);

    $this->actingAs($supervisor)
        ->patch(route('training.supervisor-review', $training), [
            'action' => 'support',
            'notes' => 'Selaras dengan pelan pembangunan pekerja.',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    expect($training->fresh()->approval_stage)->toBe('hr');
    expect(app(LeaveApprovalAlerts::class)->summarizeFor($hrManager)['training_hr'])->toBe(1);

    $this->actingAs($hrManager)
        ->patch(route('training.review', $training), [
            'action' => 'approve',
            'approved_cost' => 300,
            'notes' => 'Bajet dan kapasiti disahkan.',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    expect($training->fresh()->status)->toBe('approved')
        ->and((float) $training->fresh()->approved_cost)->toBe(300.0);

    $this->actingAs($hr)
        ->put(route('training.completion', $training), [
            'attendance_status' => 'passed',
            'attended_hours' => 8,
            'assessment_score' => 88,
            'notes' => 'Hadir penuh dan lulus penilaian.',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    expect($training->fresh()->status)->toBe('completed')
        ->and($training->fresh()->passed)->toBeTrue()
        ->and($plan->fresh()->status)->toBe('completed');

    $this->actingAs($employee)
        ->post(route('employee-training.certificate', $training), [
            'certificate' => UploadedFile::fake()->create(
                'certificate.pdf',
                120,
                'application/pdf',
            ),
            'valid_until' => '2028-08-20',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    $this->actingAs($employee)
        ->put(route('employee-training.evaluate', $training), [
            'employee_rating' => 5,
            'employee_feedback' => 'Kandungan sangat relevan dan boleh terus digunakan.',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    $this->actingAs($hr)
        ->post(route('training.competencies.save'), [
            'employee_user_id' => $employee->getKey(),
            'competency_id' => $competency->getKey(),
            'current_level' => 3,
            'assessment_source' => 'training',
            'evidence_notes' => 'Sijil dan skor penilaian 88%.',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    expect(EmployeeCompetency::query()->firstOrFail()->current_level)->toBe(3)
        ->and($training->fresh()->employee_rating)->toBe(5)
        ->and($training->fresh()->attachments()->where('attachment_type', 'certificate')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'training.completion_recorded')->exists())->toBeTrue();

    expect($legacyQueries)->not->toBeEmpty();
    foreach ($legacyQueries as $sql) {
        expect($sql)->toStartWith('select');
    }
});

test('training approval rejects costs above available departmental budget', function () {
    $employee = User::factory()->employee()->create();
    $hr = User::factory()->hrAdmin()->create();
    $hrManager = User::factory()->hrManager()->create();
    EmployeeUserLink::query()->create([
        'user_id' => $employee->getKey(),
        'employee_id' => 100,
        'office_location_id' => OfficeLocation::query()->value('id'),
        'is_active' => true,
    ]);
    TrainingBudget::query()->create([
        'year' => 2026,
        'department_id' => 1,
        'allocated_amount' => 100,
    ]);
    $training = TrainingRequest::query()->create([
        'request_number' => 'TRN-OVER-BUDGET',
        'employee_user_id' => $employee->getKey(),
        'employee_id' => 100,
        'department_id' => 1,
        'budget_year' => 2026,
        'position_name' => 'Eksekutif Teknologi Maklumat',
        'course_title' => 'Kursus Mahal',
        'justification' => 'Keperluan teknikal.',
        'development_source' => 'self',
        'estimated_cost' => 500,
        'status' => 'pending',
        'approval_stage' => 'hr',
    ]);

    $this->actingAs($hrManager)
        ->from(route('training.index'))
        ->patch(route('training.review', $training), [
            'action' => 'approve',
            'approved_cost' => 500,
            'notes' => 'Untuk semakan bajet.',
        ])
        ->assertRedirect(route('training.index'))
        ->assertSessionHasErrors('approved_cost');

    expect($training->fresh()->status)->toBe('pending');
});
