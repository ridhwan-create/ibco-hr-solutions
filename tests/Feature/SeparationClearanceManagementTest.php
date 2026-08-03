<?php

use App\Models\AuditLog;
use App\Models\ClearanceTask;
use App\Models\ClearanceTemplateItem;
use App\Models\DocumentTemplate;
use App\Models\EmployeeUserLink;
use App\Models\HandoverItem;
use App\Models\HrDocument;
use App\Models\OfficeLocation;
use App\Models\PerformanceSupervisorAssignment;
use App\Models\SeparationAsset;
use App\Models\SeparationAttachment;
use App\Models\SeparationCase;
use App\Models\SeparationTemplate;
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
        ['id' => 1, 'description' => 'Teknologi Maklumat', 'rcd_enable' => 1],
        ['id' => 2, 'description' => 'Operasi', 'rcd_enable' => 1],
    ]);
    DB::connection('ibco')->table('maklumatpekerja')->insert([
        ['id' => 100, 'employeeID' => 'EMP-100', 'nama' => 'Nur Employee', 'rcd_enable' => 1],
        ['id' => 101, 'employeeID' => 'EMP-101', 'nama' => 'Other Employee', 'rcd_enable' => 1],
    ]);
    DB::connection('ibco')->table('maklumatjawatan')->insert([
        ['id' => 1000, 'id_pekerja' => 100, 'id_department' => 1, 'jawatan' => 'Eksekutif ICT', 'rcd_enable' => 1],
        ['id' => 1001, 'id_pekerja' => 101, 'id_department' => 2, 'jawatan' => 'Eksekutif Operasi', 'rcd_enable' => 1],
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

function separationUsers(): array
{
    $employee = User::factory()->employee()->create([
        'name' => 'Nur Employee',
        'email' => 'employee@ibco.test',
    ]);
    $otherEmployee = User::factory()->employee()->create([
        'name' => 'Other Employee',
        'email' => 'other@ibco.test',
    ]);
    $supervisor = User::factory()->supervisor()->create([
        'name' => 'Penyelia Jabatan',
    ]);
    $hr = User::factory()->hrAdmin()->create(['name' => 'HR Approver']);
    $hrManager = User::factory()->hrManager()->create(['name' => 'Pengurus HR']);
    $verifier = User::factory()->hrManager()->create(['name' => 'Settlement Verifier']);
    $locationId = OfficeLocation::query()->value('id');
    EmployeeUserLink::query()->create([
        'user_id' => $employee->getKey(),
        'employee_id' => 100,
        'office_location_id' => $locationId,
        'is_active' => true,
        'created_by' => $hr->getKey(),
        'updated_by' => $hr->getKey(),
    ]);
    EmployeeUserLink::query()->create([
        'user_id' => $otherEmployee->getKey(),
        'employee_id' => 101,
        'office_location_id' => $locationId,
        'is_active' => true,
        'created_by' => $hr->getKey(),
        'updated_by' => $hr->getKey(),
    ]);
    PerformanceSupervisorAssignment::query()->create([
        'department_id' => 1,
        'supervisor_user_id' => $supervisor->getKey(),
        'is_active' => true,
        'updated_by' => $hr->getKey(),
    ]);

    return compact(
        'employee',
        'otherEmployee',
        'supervisor',
        'hr',
        'hrManager',
        'verifier',
    );
}

function separationTemplate(array $users): SeparationTemplate
{
    $template = SeparationTemplate::query()->create([
        'code' => 'RESIGNATION-TEST',
        'name' => 'Berhenti Sukarela Ujian',
        'separation_type' => 'resignation',
        'minimum_notice_days' => 30,
        'employee_can_apply' => true,
        'exit_interview_required' => true,
        'final_settlement_required' => true,
        'approver_user_id' => $users['hrManager']->getKey(),
        'is_active' => true,
    ]);
    foreach ([
        ['title' => 'Maklumat Peribadi', 'owner_type' => 'employee', 'employee_action_required' => true, 'assignee_user_id' => null],
        ['title' => 'Serahan Tugas', 'owner_type' => 'supervisor', 'employee_action_required' => true, 'assignee_user_id' => null],
        ['title' => 'Clearance HR', 'owner_type' => 'hr', 'employee_action_required' => false, 'assignee_user_id' => null],
    ] as $index => $item) {
        ClearanceTemplateItem::query()->create([
            'separation_template_id' => $template->getKey(),
            ...$item,
            'description' => 'Tugasan wajib bagi aliran ujian.',
            'due_offset_days' => 0,
            'is_mandatory' => true,
            'evidence_required' => false,
            'sort_order' => ($index + 1) * 10,
        ]);
    }
    foreach ([
        ['code' => 'EMP-RESIGN-ACK', 'name' => 'Penerimaan Notis', 'category' => 'resignation'],
        ['code' => 'EMP-CLEARANCE', 'name' => 'Selesai Clearance', 'category' => 'clearance'],
    ] as $definition) {
        DocumentTemplate::query()->create([
            ...$definition,
            'subject_template' => 'Dokumen {{employee_name}}',
            'body_template' => 'Kes {{case_number}} tamat pada {{last_working_date}}.',
            'available_variables' => ['employee_name', 'case_number', 'last_working_date'],
            'sequence_key' => 'DEFAULT',
            'requires_approval' => true,
            'approver_user_id' => $users['verifier']->getKey(),
            'acknowledgement_required' => false,
            'confidentiality' => 'confidential',
            'is_active' => true,
        ]);
    }

    return $template;
}

test('complete resignation and clearance workflow keeps db_spp read only', function () {
    $users = separationUsers();
    $template = separationTemplate($users);
    $legacyQueries = [];
    Event::listen(QueryExecuted::class, function (QueryExecuted $event) use (&$legacyQueries) {
        if ($event->connectionName === 'ibco') {
            $legacyQueries[] = strtolower(ltrim($event->sql));
        }
    });

    $this->actingAs($users['employee'])
        ->post(route('employee-separation.store'), [
            'separation_template_id' => $template->getKey(),
            'reason_category' => 'Peluang kerjaya',
            'reason_details' => 'Saya menerima peluang kerjaya baharu dan mengemukakan notis rasmi.',
            'proposed_last_day' => today()->addDays(45)->toDateString(),
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    $case = SeparationCase::query()->firstOrFail();
    expect($case->case_number)->toStartWith('SEP/2026/')
        ->and($case->status)->toBe('pending_approval')
        ->and($case->approval_stage)->toBe('supervisor')
        ->and($case->notice_shortfall_days)->toBe(0);

    $this->actingAs($users['supervisor'])
        ->patch(route('separations.supervisor-review', $case), [
            'action' => 'support',
            'notes' => 'Tarikh dan pelan serahan tugas disokong.',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    expect($case->fresh()->approval_stage)->toBe('hr');

    $this->actingAs($users['hrManager'])
        ->patch(route('separations.hr-review', $case), [
            'action' => 'approve',
            'approved_last_day' => today()->addDays(45)->toDateString(),
            'notice_waived' => false,
            'waiver_notes' => null,
            'notes' => 'Kelulusan HR diberikan.',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    expect($case->fresh()->status)->toBe('clearance')
        ->and($case->tasks()->count())->toBe(3)
        ->and($case->interview)->not->toBeNull()
        ->and($case->settlement)->not->toBeNull();

    $this->actingAs($users['hr'])
        ->post(route('separations.documents.store', $case), ['kind' => 'acceptance'])
        ->assertRedirect(route('hr-documents.index', ['search' => 'Nur Employee']));
    expect(HrDocument::query()->where('source_type', 'separation')->count())->toBe(1);

    $employeeTask = $case->tasks()->where('owner_type', 'employee')->firstOrFail();
    $supervisorTask = $case->tasks()->where('owner_type', 'supervisor')->firstOrFail();
    $hrTask = $case->tasks()->where('owner_type', 'hr')->firstOrFail();
    $this->actingAs($users['employee'])
        ->patch(route('employee-separation.tasks.submit', [$case, $employeeTask]), [
            'submission_notes' => 'Alamat dan maklumat hubungan selepas perkhidmatan telah disahkan.',
        ])
        ->assertRedirect();
    $this->actingAs($users['employee'])
        ->patch(route('employee-separation.tasks.submit', [$case, $supervisorTask]), [
            'submission_notes' => 'Semua fail projek telah disusun untuk penyelia.',
        ])
        ->assertRedirect();
    foreach ([
        [$users['hr'], $employeeTask],
        [$users['supervisor'], $supervisorTask],
        [$users['hr'], $hrTask],
    ] as [$actor, $task]) {
        $this->actingAs($actor)
            ->patch(route('separations.tasks.action', [$case, $task]), [
                'action' => 'complete',
                'notes' => 'Disahkan lengkap.',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();
    }
    expect(ClearanceTask::query()->where('status', 'completed')->count())->toBe(3);

    $this->actingAs($users['hr'])
        ->post(route('separations.assets.store', $case), [
            'asset_type' => 'ict',
            'asset_name' => 'Komputer Riba Dell',
            'asset_tag' => 'ICT-001',
            'serial_number' => 'SN-001',
            'expected_return_date' => today()->addDays(45)->toDateString(),
            'estimated_value' => 4500,
        ])
        ->assertRedirect();
    $asset = SeparationAsset::query()->firstOrFail();
    $this->actingAs($users['hr'])
        ->patch(route('separations.assets.update', [$case, $asset]), [
            'status' => 'returned',
            'return_condition' => 'good',
            'charge_amount' => 0,
            'notes' => 'Diterima lengkap.',
        ])
        ->assertRedirect();

    $this->actingAs($users['employee'])
        ->post(route('employee-separation.handovers.store', $case), [
            'title' => 'Portal Pelanggan',
            'description' => 'Kod sumber, isu terbuka dan akses vendor diserahkan.',
            'due_date' => today()->addDays(40)->toDateString(),
        ])
        ->assertRedirect();
    $handover = HandoverItem::query()->firstOrFail();
    $this->actingAs($users['employee'])
        ->patch(route('employee-separation.handovers.submit', [$case, $handover]), [
            'submission_notes' => 'Sesi serahan telah dilaksanakan.',
        ])
        ->assertRedirect();
    $this->actingAs($users['supervisor'])
        ->patch(route('separations.handovers.review', [$case, $handover]), [
            'action' => 'accept',
            'notes' => 'Serahan diterima.',
        ])
        ->assertRedirect();

    $this->actingAs($users['employee'])
        ->put(route('employee-separation.interview.submit', $case), [
            'primary_reason' => 'Peluang kerjaya',
            'employment_experience_rating' => 4,
            'manager_support_rating' => 5,
            'would_recommend' => true,
            'positive_feedback' => 'Pasukan yang saling membantu.',
            'improvement_feedback' => 'Tambah laluan pembangunan kerjaya.',
            'additional_feedback' => 'Terima kasih.',
        ])
        ->assertRedirect();
    $this->actingAs($users['hr'])
        ->put(route('separations.interview.update', $case), [
            'interviewer_user_id' => $users['hr']->getKey(),
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'hr_private_notes' => 'Maklum balas dibincangkan.',
            'completed' => true,
        ])
        ->assertRedirect();

    $this->actingAs($users['hr'])
        ->put(route('separations.settlement.update', $case), [
            'salary_due' => 3000,
            'leave_encashment' => 500,
            'gratuity' => 0,
            'claims_due' => 100,
            'other_payments' => 0,
            'notice_deduction' => 0,
            'loan_deduction' => 0,
            'other_deductions' => 0,
            'notes' => 'Pengiraan akhir disemak.',
            'submit' => true,
        ])
        ->assertRedirect();
    $this->actingAs($users['hr'])
        ->patch(route('separations.settlement.verify', $case))
        ->assertForbidden();
    $this->actingAs($users['verifier'])
        ->patch(route('separations.settlement.verify', $case))
        ->assertRedirect();
    expect($case->fresh()->status)->toBe('final_review');

    $this->actingAs($users['hrManager'])
        ->patch(route('separations.complete', $case), [
            'eligible_for_rehire' => true,
            'closure_notes' => 'Semua clearance, aset dan penyelesaian akhir lengkap.',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    expect($case->fresh()->status)->toBe('completed')
        ->and($case->fresh()->completed_at)->not->toBeNull();
    $this->actingAs($users['hr'])
        ->post(route('separations.documents.store', $case), ['kind' => 'clearance'])
        ->assertRedirect();
    expect(HrDocument::query()->where('source_type', 'separation')->count())->toBe(2)
        ->and(AuditLog::query()->where('action', 'separation.employee_submitted')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'separation.case_completed')->exists())->toBeTrue();

    expect($legacyQueries)->not->toBeEmpty();
    foreach ($legacyQueries as $sql) {
        expect($sql)->toStartWith('select');
    }
});

test('employees cannot download another employees clearance attachment', function () {
    $users = separationUsers();
    $case = SeparationCase::query()->create([
        'case_number' => 'SEP/2026/00999',
        'employee_user_id' => $users['employee']->getKey(),
        'employee_id' => 100,
        'employee_name' => 'Nur Employee',
        'separation_type' => 'resignation',
        'reason_details' => 'Notis berhenti kerja rasmi.',
        'notice_submitted_date' => today(),
        'proposed_last_day' => today()->addMonth(),
        'status' => 'clearance',
    ]);
    $attachment = SeparationAttachment::query()->create([
        'separation_case_id' => $case->getKey(),
        'context' => 'supporting',
        'disk' => 'local',
        'path' => 'separations/private.pdf',
        'original_name' => 'private.pdf',
        'mime_type' => 'application/pdf',
        'size' => 100,
        'visible_to_employee' => true,
        'uploaded_by' => $users['hr']->getKey(),
    ]);
    Storage::disk('local')->put($attachment->path, 'private');

    $this->actingAs($users['otherEmployee'])
        ->get(route('employee-separation.attachments.download', [$case, $attachment]))
        ->assertForbidden();
});

test('clearance assignees cannot download unrelated private case attachments', function () {
    $users = separationUsers();
    $clearanceUser = User::factory()->create(['name' => 'Pegawai ICT']);
    $case = SeparationCase::query()->create([
        'case_number' => 'SEP/2026/00998',
        'employee_user_id' => $users['employee']->getKey(),
        'employee_id' => 100,
        'employee_name' => 'Nur Employee',
        'separation_type' => 'resignation',
        'reason_details' => 'Notis berhenti kerja rasmi.',
        'notice_submitted_date' => today(),
        'proposed_last_day' => today()->addMonth(),
        'status' => 'clearance',
    ]);
    ClearanceTask::query()->create([
        'separation_case_id' => $case->getKey(),
        'title' => 'Pemulangan Peralatan ICT',
        'owner_type' => 'ict',
        'assigned_user_id' => $clearanceUser->getKey(),
        'status' => 'pending',
    ]);
    $attachment = SeparationAttachment::query()->create([
        'separation_case_id' => $case->getKey(),
        'context' => 'final_settlement',
        'disk' => 'local',
        'path' => 'separations/private-settlement.pdf',
        'original_name' => 'private-settlement.pdf',
        'mime_type' => 'application/pdf',
        'size' => 100,
        'visible_to_employee' => false,
        'uploaded_by' => $users['hr']->getKey(),
    ]);
    Storage::disk('local')->put($attachment->path, 'private');

    $this->actingAs($clearanceUser)
        ->get(route('separations.attachments.download', [$case, $attachment]))
        ->assertForbidden();
});

test('hr creator cannot approve an hr initiated separation case', function () {
    $users = separationUsers();
    $template = separationTemplate($users);
    $case = SeparationCase::query()->create([
        'case_number' => 'SEP/2026/00888',
        'separation_template_id' => $template->getKey(),
        'employee_user_id' => $users['employee']->getKey(),
        'employee_id' => 100,
        'employee_name' => 'Nur Employee',
        'separation_type' => 'termination',
        'initiated_by_employee' => false,
        'reason_details' => 'Penamatan oleh organisasi.',
        'notice_submitted_date' => today(),
        'proposed_last_day' => today()->addMonth(),
        'status' => 'pending_approval',
        'approval_stage' => 'hr',
        'hr_approver_user_id' => $users['hrManager']->getKey(),
        'created_by' => $users['hrManager']->getKey(),
    ]);

    $this->actingAs($users['hrManager'])
        ->patch(route('separations.hr-review', $case), [
            'action' => 'approve',
            'approved_last_day' => today()->addMonth()->toDateString(),
            'notice_waived' => false,
        ])
        ->assertForbidden();
});
