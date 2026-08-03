<?php

use App\Models\AuditLog;
use App\Models\EmployeeRecord;
use App\Models\EmployeeUserLink;
use App\Models\OfficeLocation;
use App\Models\OnboardingCase;
use App\Models\OnboardingTemplate;
use App\Models\RecruitmentCandidate;
use App\Models\RecruitmentInterview;
use App\Models\RecruitmentOffer;
use App\Models\RecruitmentRequisition;
use App\Models\User;
use App\Support\LeaveApprovalAlerts;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

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

    Schema::connection('ibco')->create('xdepartment', function (
        Blueprint $table,
    ) {
        $table->increments('id');
        $table->string('description')->nullable();
        $table->boolean('rcd_enable')->default(true);
    });
    Schema::connection('ibco')->create('maklumatjawatan', function (
        Blueprint $table,
    ) {
        $table->increments('id');
        $table->integer('id_pekerja')->nullable();
        $table->integer('id_department')->nullable();
        $table->string('jawatan')->nullable();
        $table->boolean('rcd_enable')->default(true);
    });
    Schema::connection('ibco')->create('maklumatpekerja', function (
        Blueprint $table,
    ) {
        $table->increments('id');
        $table->string('employeeID', 30)->nullable();
        $table->string('nama')->nullable();
        $table->string('nric', 40)->nullable();
        $table->string('email')->nullable();
        $table->boolean('rcd_enable')->default(true);
    });
    DB::connection('ibco')->table('xdepartment')->insert([
        'id' => 1,
        'description' => 'Teknologi Maklumat',
        'rcd_enable' => 1,
    ]);
    DB::connection('ibco')->table('maklumatjawatan')->insert([
        'id' => 1,
        'id_pekerja' => null,
        'id_department' => 1,
        'jawatan' => 'Eksekutif Teknologi Maklumat',
        'rcd_enable' => 1,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
    DB::disconnect('ibco');
});

function createPublishedRequisition(
    User $hr,
    User $manager,
    User $hrManager,
): RecruitmentRequisition
{
    test()->actingAs($hr)
        ->post(route('recruitment.requisitions.store'), [
            'code' => 'REQ-IT-001',
            'title' => 'Eksekutif Teknologi Maklumat',
            'department_id' => 1,
            'position_name' => 'Eksekutif Teknologi Maklumat',
            'employment_type' => 'permanent',
            'vacancies' => 1,
            'hiring_manager_user_id' => $manager->getKey(),
            'location' => 'Kuala Lumpur',
            'description' => 'Mengurus sistem dan projek ICT organisasi.',
            'requirements' => 'Ijazah dalam bidang Teknologi Maklumat.',
            'min_salary' => 3500,
            'max_salary' => 5000,
            'target_hire_date' => '2026-09-01',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    $requisition = RecruitmentRequisition::query()->firstOrFail();
    test()->actingAs($hrManager)
        ->patch(route('recruitment.requisitions.status', $requisition), [
            'action' => 'submit',
        ])
        ->assertRedirect();
    test()->actingAs($hr)
        ->patch(route('recruitment.requisitions.status', $requisition), [
            'action' => 'approve',
            'notes' => 'Perjawatan dan bajet disahkan.',
        ])
        ->assertRedirect();
    test()->actingAs($hr)
        ->patch(route('recruitment.requisitions.status', $requisition), [
            'action' => 'publish',
        ])
        ->assertRedirect();

    return $requisition->fresh();
}

function createOnboardingTemplate(User $hr): OnboardingTemplate
{
    test()->actingAs($hr)
        ->post(route('recruitment-settings.templates.store'), [
            'code' => 'ONB-IT',
            'name' => 'Onboarding ICT',
            'department_id' => 1,
            'position_name' => 'Eksekutif Teknologi Maklumat',
            'description' => 'Checklist pengambilan staf ICT.',
            'is_active' => true,
            'tasks' => [
                [
                    'title' => 'Lengkapkan borang peribadi',
                    'description' => 'Semak maklumat peribadi dan waris.',
                    'category' => 'employee',
                    'assignee_role' => 'employee',
                    'due_offset_days' => -3,
                    'is_required' => true,
                ],
                [
                    'title' => 'Sediakan komputer riba',
                    'description' => 'Sediakan aset dan akses sistem.',
                    'category' => 'it',
                    'assignee_role' => 'custom',
                    'due_offset_days' => 0,
                    'is_required' => true,
                ],
            ],
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    return OnboardingTemplate::query()->with('tasks')->firstOrFail();
}

test('complete recruitment offer and onboarding workflow uses legacy database as read only', function () {
    $hr = User::factory()->hrAdmin()->create();
    $hrManager = User::factory()->hrManager()->create();
    $manager = User::factory()->supervisor()->create();
    $employeeUser = User::factory()->employee()->create([
        'email' => 'new.employee@example.com',
    ]);
    $requisition = createPublishedRequisition($hr, $manager, $hrManager);
    $template = createOnboardingTemplate($hr);
    $legacyEmployeeId = DB::connection('ibco')
        ->table('maklumatpekerja')
        ->insertGetId([
            'employeeID' => 'EMP-NEW-001',
            'nama' => 'Nur Calon Berjaya',
            'rcd_enable' => 1,
        ]);
    $office = OfficeLocation::query()->create([
        'name' => 'IBCO Recruitment HQ',
        'address' => 'Kuala Lumpur',
        'latitude' => 3.139,
        'longitude' => 101.6869,
        'radius_meters' => 100,
        'accuracy_limit_meters' => 100,
        'is_active' => true,
    ]);
    $legacyQueries = [];
    Event::listen(QueryExecuted::class, function (QueryExecuted $event) use (
        &$legacyQueries,
    ) {
        if ($event->connectionName === 'ibco') {
            $legacyQueries[] = strtolower(ltrim($event->sql));
        }
    });

    $this->actingAs($hr)
        ->post(route('recruitment.candidates.store'), [
            'recruitment_requisition_id' => $requisition->getKey(),
            'name' => 'Nur Calon Berjaya',
            'email' => 'candidate@example.com',
            'phone' => '0123456789',
            'current_company' => 'Syarikat Lama',
            'current_position' => 'IT Executive',
            'expected_salary' => 4500,
            'notice_period_days' => 30,
            'source' => 'job_portal',
            'owner_user_id' => $hr->getKey(),
            'screening_notes' => 'Pengalaman memenuhi syarat.',
            'resume' => UploadedFile::fake()->create(
                'resume.pdf',
                200,
                'application/pdf',
            ),
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    $candidate = RecruitmentCandidate::query()->firstOrFail();
    expect($candidate->stage)->toBe('applied');
    expect($candidate->documents()->count())->toBe(1);

    $this->actingAs($hr)
        ->post(route('recruitment.interviews.store', $candidate), [
            'round' => 1,
            'interview_type' => 'physical',
            'scheduled_at' => '2026-08-05 10:00:00',
            'duration_minutes' => 60,
            'location_or_link' => 'Bilik Mesyuarat Utama',
            'panel_user_ids' => [$manager->getKey()],
            'notes' => 'Temu duga kompetensi dan teknikal.',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    $interview = RecruitmentInterview::query()->firstOrFail();
    expect($candidate->fresh()->stage)->toBe('interview');

    $this->actingAs($manager)
        ->put(route('recruitment.interviews.scorecard', [$candidate, $interview]), [
            'technical_score' => 4.5,
            'communication_score' => 4,
            'culture_score' => 4,
            'recommendation' => 'strong_yes',
            'strengths' => 'Pengalaman teknikal dan komunikasi yang baik.',
            'concerns' => null,
            'comments' => 'Disyorkan untuk tawaran.',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    expect($interview->fresh()->status)->toBe('completed');
    expect((float) $interview->fresh()->overall_score)->toBe(4.17);

    $this->actingAs($hr)
        ->post(route('recruitment.offers.store', $candidate), [
            'position_name' => 'Eksekutif Teknologi Maklumat',
            'department_id' => 1,
            'employment_type' => 'permanent',
            'salary' => 4600,
            'start_date' => '2026-09-01',
            'probation_months' => 3,
            'expiry_date' => '2026-08-15',
            'terms' => 'Tertakluk kepada pemeriksaan kesihatan.',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    $offer = RecruitmentOffer::query()->firstOrFail();
    foreach (['submit', 'approve', 'send'] as $action) {
        $actor = $action === 'approve' ? $hrManager : $hr;
        $this->actingAs($actor)
            ->patch(route('recruitment.offers.status', [$candidate, $offer]), [
                'action' => $action,
                'notes' => $action === 'approve'
                    ? 'Struktur gaji diluluskan.'
                    : null,
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();
        $offer->refresh();
    }
    $this->actingAs($hr)
        ->patch(route('recruitment.offers.status', [$candidate, $offer]), [
            'action' => 'accept',
            'notes' => 'Calon menerima tawaran.',
            'onboarding_template_id' => $template->getKey(),
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    $onboarding = OnboardingCase::query()->with('tasks')->firstOrFail();
    expect($candidate->fresh()->stage)->toBe('hired');
    expect($offer->fresh()->status)->toBe('accepted');
    expect($onboarding->tasks)->toHaveCount(2);

    $this->actingAs($hr)
        ->put(route('onboarding.link-employee', $onboarding), [
            'legacy_employee_id' => $legacyEmployeeId,
            'employee_user_id' => $employeeUser->getKey(),
            'office_location_id' => $office->getKey(),
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    expect(EmployeeUserLink::query()
        ->where('user_id', $employeeUser->getKey())
        ->where('employee_id', $legacyEmployeeId)
        ->exists())->toBeTrue();

    $this->actingAs($hr)
        ->patch(route('onboarding.status', $onboarding), ['action' => 'start'])
        ->assertRedirect();
    $employeeTask = $onboarding->fresh('tasks')->tasks
        ->firstWhere('assignee_role', 'employee');
    $this->actingAs($employeeUser)
        ->patch(route('employee-onboarding.tasks.update', $employeeTask), [
            'status' => 'completed',
            'completion_notes' => 'Borang telah disemak dan dilengkapkan.',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    $hrTask = $onboarding->fresh('tasks')->tasks
        ->firstWhere('assignee_role', 'custom');
    $this->actingAs($hr)
        ->put(route('onboarding.tasks.update', [$onboarding, $hrTask]), [
            'status' => 'completed',
            'assignee_user_id' => $hr->getKey(),
            'completion_notes' => 'Komputer riba dan akaun telah disediakan.',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    $this->actingAs($hrManager)
        ->patch(route('onboarding.status', $onboarding), ['action' => 'complete'])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    expect($onboarding->fresh()->status)->toBe('completed');
    expect(AuditLog::query()
        ->where('action', 'onboarding.case_complete')
        ->exists())->toBeTrue();
    expect($legacyQueries)->not->toBeEmpty();
    expect(collect($legacyQueries)->every(
        fn (string $sql) => str_starts_with($sql, 'select'),
    ))->toBeTrue();
});

test('HR can correct a wrong legacy onboarding link without deleting source records', function () {
    $hr = User::factory()->hrAdmin()->create();
    $hrManager = User::factory()->hrManager()->create();
    $manager = User::factory()->supervisor()->create();
    $employeeUser = User::factory()->employee()->create([
        'name' => 'DUMMY DATA',
        'email' => 'dummy.employee@example.com',
    ]);
    $requisition = createPublishedRequisition($hr, $manager, $hrManager);
    $legacyEmployeeId = DB::connection('ibco')
        ->table('maklumatpekerja')
        ->insertGetId([
            'employeeID' => 'DUMMY-008',
            'nama' => 'DUMMY DATA',
            'rcd_enable' => 1,
        ]);
    $office = OfficeLocation::query()->create([
        'name' => 'IBCO HQ',
        'address' => 'Kuala Lumpur',
        'latitude' => 3.139,
        'longitude' => 101.6869,
        'radius_meters' => 100,
        'accuracy_limit_meters' => 100,
        'is_active' => true,
    ]);
    $candidate = RecruitmentCandidate::query()->create([
        'recruitment_requisition_id' => $requisition->getKey(),
        'candidate_number' => 'CAN-UNLINK-0001',
        'name' => 'Calon Sebenar',
        'email' => 'calon.sebenar@example.com',
        'phone' => '0123456789',
        'nric' => '900101101234',
        'source' => 'job_portal',
        'stage' => 'hired',
        'owner_user_id' => $hr->getKey(),
        'applied_at' => now(),
        'hired_at' => now(),
    ]);
    $offer = RecruitmentOffer::query()->create([
        'recruitment_candidate_id' => $candidate->getKey(),
        'offer_number' => 'OFF-UNLINK-0001',
        'position_name' => 'Eksekutif Teknologi Maklumat',
        'department_id' => 1,
        'employment_type' => 'permanent',
        'salary' => 4600,
        'start_date' => '2026-09-01',
        'probation_months' => 3,
        'expiry_date' => '2026-08-15',
        'status' => 'accepted',
        'created_by' => $hr->getKey(),
        'approved_by' => $hr->getKey(),
        'responded_at' => now(),
    ]);
    $onboarding = OnboardingCase::query()->create([
        'recruitment_candidate_id' => $candidate->getKey(),
        'recruitment_offer_id' => $offer->getKey(),
        'manager_user_id' => $manager->getKey(),
        'start_date' => '2026-09-01',
        'status' => 'active',
        'created_by' => $hr->getKey(),
        'started_at' => now(),
    ]);
    $task = $onboarding->tasks()->create([
        'title' => 'Lengkapkan maklumat pekerja',
        'category' => 'employee',
        'assignee_role' => 'employee',
        'due_date' => '2026-09-01',
        'is_required' => true,
        'status' => 'pending',
        'sort_order' => 1,
    ]);

    $this->actingAs($hr)
        ->put(route('onboarding.link-employee', $onboarding), [
            'legacy_employee_id' => $legacyEmployeeId,
            'employee_user_id' => $employeeUser->getKey(),
            'office_location_id' => $office->getKey(),
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    $task->update([
        'status' => 'completed',
        'completion_notes' => 'Disiapkan melalui pautan yang salah.',
        'completed_by' => $employeeUser->getKey(),
        'completed_at' => now(),
    ]);

    $legacyQueries = [];
    Event::listen(QueryExecuted::class, function (QueryExecuted $event) use (
        &$legacyQueries,
    ) {
        if ($event->connectionName === 'ibco') {
            $legacyQueries[] = strtolower(ltrim($event->sql));
        }
    });

    $this->actingAs($hr)
        ->from(route('onboarding.index'))
        ->delete(route('onboarding.unlink-employee', $onboarding), [
            'reason' => 'Calon tersalah dipautkan kepada rekod DUMMY DATA.',
            'deactivate_employee_link' => false,
            'confirmed' => true,
        ])
        ->assertRedirect(route('onboarding.index'))
        ->assertSessionDoesntHaveErrors();

    expect($onboarding->fresh()->legacy_employee_id)->toBeNull()
        ->and($onboarding->fresh()->employee_user_id)->toBeNull()
        ->and($task->fresh()->assignee_user_id)->toBeNull()
        ->and($task->fresh()->status)->toBe('pending')
        ->and($task->fresh()->completion_notes)->toBeNull()
        ->and($task->fresh()->completed_by)->toBeNull()
        ->and($task->fresh()->completed_at)->toBeNull()
        ->and($employeeUser->fresh())->not->toBeNull();
    expect(EmployeeUserLink::query()
        ->where('user_id', $employeeUser->getKey())
        ->where('employee_id', $legacyEmployeeId)
        ->where('is_active', true)
        ->exists())->toBeTrue();
    expect(AuditLog::query()
        ->where('action', 'onboarding.employee_unlinked')
        ->where('auditable_id', (string) $onboarding->getKey())
        ->exists())->toBeTrue();

    $this->actingAs($hr)
        ->put(route('onboarding.link-employee', $onboarding), [
            'legacy_employee_id' => $legacyEmployeeId,
            'employee_user_id' => $employeeUser->getKey(),
            'office_location_id' => $office->getKey(),
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    $this->actingAs($hr)
        ->delete(route('onboarding.unlink-employee', $onboarding), [
            'reason' => 'Pautan akaun Employee juga merupakan data ujian yang salah.',
            'deactivate_employee_link' => true,
            'confirmed' => true,
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    expect(EmployeeUserLink::query()
        ->where('user_id', $employeeUser->getKey())
        ->where('employee_id', $legacyEmployeeId)
        ->where('is_active', false)
        ->exists())->toBeTrue();
    expect(AuditLog::query()
        ->where('action', 'employee_link.deactivated_from_onboarding_correction')
        ->exists())->toBeTrue();
    expect(DB::connection('ibco')
        ->table('maklumatpekerja')
        ->where('id', $legacyEmployeeId)
        ->where('nama', 'DUMMY DATA')
        ->exists())->toBeTrue();
    expect(collect($legacyQueries)->every(
        fn (string $sql) => str_starts_with($sql, 'select'),
    ))->toBeTrue();

    $this->actingAs($employeeUser)
        ->delete(route('onboarding.unlink-employee', $onboarding), [
            'reason' => 'Cubaan tanpa kebenaran.',
            'confirmed' => true,
        ])
        ->assertForbidden();
});

test('accepted candidate is registered automatically without writing to legacy database', function () {
    $hr = User::factory()->hrAdmin()->create();
    $hrManager = User::factory()->hrManager()->create();
    $manager = User::factory()->supervisor()->create();
    $requisition = createPublishedRequisition($hr, $manager, $hrManager);
    $office = OfficeLocation::query()->create([
        'name' => 'IBCO HQ',
        'address' => 'Kuala Lumpur',
        'latitude' => 3.139,
        'longitude' => 101.6869,
        'radius_meters' => 100,
        'accuracy_limit_meters' => 100,
        'is_active' => true,
    ]);
    $candidate = RecruitmentCandidate::query()->create([
        'recruitment_requisition_id' => $requisition->getKey(),
        'candidate_number' => 'CAN-202607-0099',
        'name' => 'Nur Pekerja Automatik',
        'email' => 'nur.candidate@example.com',
        'phone' => '0123456789',
        'nric' => '900101101234',
        'source' => 'job_portal',
        'stage' => 'hired',
        'owner_user_id' => $hr->getKey(),
        'applied_at' => now(),
        'hired_at' => now(),
    ]);
    $offer = RecruitmentOffer::query()->create([
        'recruitment_candidate_id' => $candidate->getKey(),
        'offer_number' => 'OFF-202607-0099',
        'position_name' => 'Eksekutif Teknologi Maklumat',
        'department_id' => 1,
        'employment_type' => 'permanent',
        'salary' => 4600,
        'start_date' => '2026-09-01',
        'probation_months' => 3,
        'expiry_date' => '2026-08-15',
        'status' => 'accepted',
        'created_by' => $hr->getKey(),
        'approved_by' => $hr->getKey(),
        'responded_at' => now(),
    ]);
    $onboarding = OnboardingCase::query()->create([
        'recruitment_candidate_id' => $candidate->getKey(),
        'recruitment_offer_id' => $offer->getKey(),
        'manager_user_id' => $manager->getKey(),
        'start_date' => '2026-09-01',
        'status' => 'pending',
        'created_by' => $hr->getKey(),
    ]);
    $task = $onboarding->tasks()->create([
        'title' => 'Lengkapkan maklumat pekerja',
        'category' => 'employee',
        'assignee_role' => 'employee',
        'due_date' => '2026-09-01',
        'is_required' => true,
        'status' => 'pending',
        'sort_order' => 1,
    ]);
    $legacyQueries = [];
    Event::listen(QueryExecuted::class, function (QueryExecuted $event) use (
        &$legacyQueries,
    ) {
        if ($event->connectionName === 'ibco') {
            $legacyQueries[] = strtolower(ltrim($event->sql));
        }
    });

    $response = $this->actingAs($hrManager)
        ->post(route('onboarding.register-employee', $onboarding), [
            'identity_number' => '900101-10-1234',
            'employee_number' => 'IBCO-2026-0099',
            'official_email' => 'nur.employee@ibco.test',
            'office_location_id' => $office->getKey(),
            'manager_user_id' => $manager->getKey(),
            'confirmed' => true,
        ]);

    $response
        ->assertRedirect(route('onboarding.index'))
        ->assertSessionDoesntHaveErrors()
        ->assertSessionHas('new_employee_credentials');
    $employee = EmployeeRecord::query()->firstOrFail();
    $employeeUser = User::query()->where('email', 'nur.employee@ibco.test')->firstOrFail();
    $credentials = session('new_employee_credentials');

    expect($employee->employee_number)->toBe('IBCO-2026-0099')
        ->and($employee->identity_number)->toBe('900101101234')
        ->and($employee->directory_id)->toBe(EmployeeRecord::DIRECTORY_ID_OFFSET + $employee->getKey())
        ->and($employee->status)->toBe('pending_activation')
        ->and($employeeUser->hasRole('employee'))->toBeTrue()
        ->and($employeeUser->account_status)->toBe('pending_activation')
        ->and($employeeUser->must_change_password)->toBeTrue()
        ->and(Hash::check($credentials['temporary_password'], $employeeUser->password))->toBeTrue()
        ->and($onboarding->fresh()->employee_record_id)->toBe($employee->getKey())
        ->and($onboarding->fresh()->employee_user_id)->toBe($employeeUser->getKey())
        ->and($task->fresh()->assignee_user_id)->toBe($employeeUser->getKey());
    expect(EmployeeUserLink::query()
        ->where('user_id', $employeeUser->getKey())
        ->where('employee_id', $employee->directory_id)
        ->where('employee_source', 'local')
        ->where('employee_record_id', $employee->getKey())
        ->exists())->toBeTrue();
    expect(AuditLog::query()
        ->where('action', 'employee.registered_from_recruitment')
        ->exists())->toBeTrue();
    expect($legacyQueries)->not->toBeEmpty();
    expect(collect($legacyQueries)->every(
        fn (string $sql) => str_starts_with($sql, 'select'),
    ))->toBeTrue();

    Carbon::setTestNow('2026-09-01 00:10:00');
    expect($employeeUser->fresh()->activateIfDue())->toBeTrue()
        ->and($employeeUser->fresh()->account_status)->toBe('active')
        ->and($employee->fresh()->status)->toBe('active');
});

test('automatic registration blocks matching legacy employee and directs HR to link existing record', function () {
    $hr = User::factory()->hrAdmin()->create();
    $hrManager = User::factory()->hrManager()->create();
    $manager = User::factory()->supervisor()->create();
    $requisition = createPublishedRequisition($hr, $manager, $hrManager);
    $office = OfficeLocation::query()->create([
        'name' => 'IBCO HQ',
        'latitude' => 3.139,
        'longitude' => 101.6869,
        'radius_meters' => 100,
        'accuracy_limit_meters' => 100,
        'is_active' => true,
    ]);
    DB::connection('ibco')->table('maklumatpekerja')->insert([
        'employeeID' => 'IBCO-LEGACY-01',
        'nama' => 'Pekerja Sedia Ada',
        'nric' => '880101101111',
        'email' => 'legacy@ibco.test',
        'rcd_enable' => 1,
    ]);
    $candidate = RecruitmentCandidate::query()->create([
        'recruitment_requisition_id' => $requisition->getKey(),
        'candidate_number' => 'CAN-DUPLICATE-01',
        'name' => 'Pekerja Sedia Ada',
        'email' => 'legacy@ibco.test',
        'phone' => '0120000000',
        'nric' => '880101101111',
        'source' => 'direct',
        'stage' => 'hired',
        'applied_at' => now(),
        'hired_at' => now(),
    ]);
    $offer = RecruitmentOffer::query()->create([
        'recruitment_candidate_id' => $candidate->getKey(),
        'offer_number' => 'OFF-DUPLICATE-01',
        'position_name' => 'Eksekutif Teknologi Maklumat',
        'employment_type' => 'permanent',
        'salary' => 4500,
        'start_date' => '2026-09-01',
        'probation_months' => 3,
        'expiry_date' => '2026-08-15',
        'status' => 'accepted',
        'created_by' => $hr->getKey(),
    ]);
    $onboarding = OnboardingCase::query()->create([
        'recruitment_candidate_id' => $candidate->getKey(),
        'recruitment_offer_id' => $offer->getKey(),
        'start_date' => '2026-09-01',
        'status' => 'pending',
        'created_by' => $hr->getKey(),
    ]);

    $this->actingAs($hrManager)
        ->from(route('onboarding.index'))
        ->post(route('onboarding.register-employee', $onboarding), [
            'identity_number' => '880101101111',
            'employee_number' => 'IBCO-2026-0100',
            'official_email' => 'new.login@ibco.test',
            'office_location_id' => $office->getKey(),
            'confirmed' => true,
        ])
        ->assertRedirect(route('onboarding.index'))
        ->assertSessionHasErrors('registration');

    expect(EmployeeRecord::query()->count())->toBe(0)
        ->and(User::query()->where('email', 'new.login@ibco.test')->exists())->toBeFalse();
});

test('candidate documents and profiles are restricted to assigned recruitment users', function () {
    $hr = User::factory()->hrAdmin()->create();
    $hrManager = User::factory()->hrManager()->create();
    $manager = User::factory()->supervisor()->create();
    $unassignedViewer = User::factory()->create();
    $requisition = createPublishedRequisition($hr, $manager, $hrManager);
    $candidate = RecruitmentCandidate::query()->create([
        'recruitment_requisition_id' => $requisition->getKey(),
        'candidate_number' => 'CAN-202607-0001',
        'name' => 'Calon Sulit',
        'email' => 'private@example.com',
        'phone' => '0111111111',
        'source' => 'direct',
        'stage' => 'screening',
        'owner_user_id' => $hr->getKey(),
        'applied_at' => now(),
    ]);
    $document = $candidate->documents()->create([
        'document_type' => 'resume',
        'disk' => 'local',
        'path' => "private/recruitment/{$candidate->getKey()}/resume.pdf",
        'original_name' => 'resume.pdf',
        'mime_type' => 'application/pdf',
        'size' => 100,
        'uploaded_by' => $hr->getKey(),
    ]);
    Storage::disk('local')->put($document->path, 'private');

    $this->actingAs($manager)
        ->get(route('recruitment.show', $candidate))
        ->assertOk();
    $this->actingAs($unassignedViewer)
        ->get(route('recruitment.show', $candidate))
        ->assertForbidden();
    $this->actingAs($unassignedViewer)
        ->get(route('recruitment.documents.download', [$candidate, $document]))
        ->assertForbidden();
    $this->actingAs($manager)
        ->get(route('recruitment.documents.download', [$candidate, $document]))
        ->assertDownload('resume.pdf');
});

test('approval alert includes recruitment approvals interviews and overdue onboarding', function () {
    $hr = User::factory()->hrAdmin()->create();
    $hrManager = User::factory()->hrManager()->create();
    $manager = User::factory()->supervisor()->create();
    $requisition = RecruitmentRequisition::query()->create([
        'code' => 'REQ-ALERT',
        'title' => 'Jawatan Alert',
        'employment_type' => 'contract',
        'vacancies' => 1,
        'description' => 'Deskripsi.',
        'requirements' => 'Syarat.',
        'status' => 'pending_approval',
        'created_by' => $hr->getKey(),
    ]);
    $candidate = RecruitmentCandidate::query()->create([
        'recruitment_requisition_id' => $requisition->getKey(),
        'candidate_number' => 'CAN-ALERT-001',
        'name' => 'Calon Alert',
        'email' => 'alert@example.com',
        'phone' => '0120000000',
        'source' => 'direct',
        'stage' => 'interview',
        'applied_at' => now(),
    ]);
    RecruitmentInterview::query()->create([
        'recruitment_candidate_id' => $candidate->getKey(),
        'round' => 1,
        'interview_type' => 'video',
        'scheduled_at' => now()->addDay(),
        'duration_minutes' => 45,
        'panel_user_ids' => [$manager->getKey()],
        'status' => 'scheduled',
        'created_by' => $hr->getKey(),
    ]);

    $hrSummary = app(LeaveApprovalAlerts::class)->summarizeFor($hrManager);
    $managerSummary = app(LeaveApprovalAlerts::class)->summarizeFor($manager);

    expect($hrSummary['recruitment_approval'])->toBe(1);
    expect($hrSummary['recruitment_total'])->toBe(1);
    expect($managerSummary['recruitment_interview'])->toBe(1);
    expect($managerSummary['recruitment_total'])->toBe(1);
});

test('recruitment and onboarding pages render with their expected data', function () {
    $hr = User::factory()->hrAdmin()->create();

    $this->actingAs($hr)
        ->get(route('recruitment.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Recruitment/Index')
            ->has('statistics')
            ->has('pipeline')
            ->has('permissions'));
    $this->actingAs($hr)
        ->get(route('onboarding.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Onboarding/Index')
            ->has('statistics')
            ->has('legacyEmployees')
            ->has('officeLocations'));
    $this->actingAs($hr)
        ->get(route('recruitment-settings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('RecruitmentSettings/Index')
            ->has('templates')
            ->has('departments'));
});
