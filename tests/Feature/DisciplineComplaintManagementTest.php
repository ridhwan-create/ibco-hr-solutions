<?php

use App\Models\AuditLog;
use App\Models\ComplaintCategory;
use App\Models\DisciplineAppeal;
use App\Models\DisciplineAttachment;
use App\Models\DisciplineCase;
use App\Models\DisciplineCaseMember;
use App\Models\EmployeeUserLink;
use App\Models\OfficeLocation;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
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
        $table->string('description');
        $table->boolean('rcd_enable')->default(true);
    });
    Schema::connection('ibco')->create('maklumatpekerja', function (Blueprint $table) {
        $table->increments('id');
        $table->string('employeeID', 40)->nullable();
        $table->string('nama');
        $table->boolean('rcd_enable')->default(true);
    });
    Schema::connection('ibco')->create('maklumatjawatan', function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('id_pekerja');
        $table->unsignedInteger('id_department')->nullable();
        $table->string('jawatan')->nullable();
        $table->boolean('rcd_enable')->default(true);
    });
    DB::connection('ibco')->table('xdepartment')->insert([
        ['id' => 1, 'description' => 'Teknologi Maklumat', 'rcd_enable' => 1],
        ['id' => 2, 'description' => 'Operasi', 'rcd_enable' => 1],
    ]);
    DB::connection('ibco')->table('maklumatpekerja')->insert([
        ['id' => 100, 'employeeID' => 'EMP-100', 'nama' => 'Nur Pengadu', 'rcd_enable' => 1],
        ['id' => 200, 'employeeID' => 'EMP-200', 'nama' => 'Ali Responden', 'rcd_enable' => 1],
    ]);
    DB::connection('ibco')->table('maklumatjawatan')->insert([
        ['id' => 1000, 'id_pekerja' => 100, 'id_department' => 1, 'jawatan' => 'Eksekutif IT', 'rcd_enable' => 1],
        ['id' => 2000, 'id_pekerja' => 200, 'id_department' => 2, 'jawatan' => 'Eksekutif Operasi', 'rcd_enable' => 1],
    ]);
    OfficeLocation::query()->create([
        'name' => 'IBCO Solutions HQ',
        'address' => 'Kuala Lumpur',
        'latitude' => 3.139,
        'longitude' => 101.6869,
        'radius_meters' => 100,
        'accuracy_limit_meters' => 100,
        'is_active' => true,
    ]);
    ComplaintCategory::query()->create([
        'code' => 'MISCONDUCT',
        'name' => 'Salah Laku',
        'description' => 'Pelanggaran tatakelakuan.',
        'default_severity' => 'high',
        'sla_days' => 21,
        'appeal_days' => 14,
        'requires_show_cause' => true,
        'allow_protected_identity' => true,
        'is_active' => true,
    ]);
});

afterEach(function () {
    DB::disconnect('ibco');
});

function disciplineUsers(): array
{
    $complainant = User::factory()->employee()->create([
        'name' => 'Nur Pengadu',
        'email' => 'pengadu@ibco.test',
    ]);
    $subject = User::factory()->employee()->create([
        'name' => 'Ali Responden',
        'email' => 'responden@ibco.test',
    ]);
    $hr = User::factory()->hrAdmin()->create(['name' => 'HR Case Manager']);
    $investigator = User::factory()->supervisor()->create(['name' => 'Pegawai Siasatan']);
    $decisionMaker = User::factory()->hrManager()->create(['name' => 'Pelulus Tatatertib']);
    $appealReviewer = User::factory()->hrManager()->create(['name' => 'Panel Rayuan']);
    $locationId = OfficeLocation::query()->value('id');
    EmployeeUserLink::query()->create([
        'user_id' => $complainant->getKey(),
        'employee_id' => 100,
        'office_location_id' => $locationId,
        'is_active' => true,
        'created_by' => $hr->getKey(),
        'updated_by' => $hr->getKey(),
    ]);
    EmployeeUserLink::query()->create([
        'user_id' => $subject->getKey(),
        'employee_id' => 200,
        'office_location_id' => $locationId,
        'is_active' => true,
        'created_by' => $hr->getKey(),
        'updated_by' => $hr->getKey(),
    ]);

    return compact(
        'complainant',
        'subject',
        'hr',
        'investigator',
        'decisionMaker',
        'appealReviewer',
    );
}

function submitDisciplineComplaint(array $users): DisciplineCase
{
    test()->actingAs($users['complainant'])
        ->post(route('employee-discipline.store'), [
            'complaint_category_id' => ComplaintCategory::query()->value('id'),
            'subject_user_id' => $users['subject']->getKey(),
            'title' => 'Pelanggaran tatakelakuan semasa tugasan',
            'incident_at' => now()->subHour()->format('Y-m-d H:i:s'),
            'incident_location' => 'IBCO Solutions HQ',
            'description' => 'Responden didakwa melanggar tatakelakuan semasa menjalankan tugasan rasmi dan saksi berada di lokasi.',
            'requested_resolution' => 'Siasatan yang adil dan tindakan sewajarnya.',
            'identity_protected' => true,
            'attachments' => [
                UploadedFile::fake()->create('bukti-awal.pdf', 120, 'application/pdf'),
            ],
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    return DisciplineCase::query()->firstOrFail();
}

test('complete discipline workflow preserves db_spp as read only', function () {
    $users = disciplineUsers();
    $legacyQueries = [];
    Event::listen(QueryExecuted::class, function (QueryExecuted $event) use (&$legacyQueries) {
        if ($event->connectionName === 'ibco') {
            $legacyQueries[] = strtolower(ltrim($event->sql));
        }
    });
    $case = submitDisciplineComplaint($users);
    expect($case->case_number)->toStartWith('D&A/')
        ->and($case->identity_protected)->toBeTrue()
        ->and($case->status)->toBe('submitted')
        ->and($case->attachments()->count())->toBe(1);

    $this->actingAs($users['hr'])
        ->patch(route('discipline.triage', $case), [
            'action' => 'accept',
            'severity' => 'high',
            'triage_notes' => 'Aduan mempunyai fakta minimum dan perlu disiasat.',
            'investigator_user_id' => $users['investigator']->getKey(),
            'allegation_summary' => 'Pelanggaran tatakelakuan semasa tugasan rasmi.',
            'target_completion_date' => today()->addDays(19)->toDateString(),
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    $member = DisciplineCaseMember::query()->firstOrFail();
    expect($case->fresh()->status)->toBe('investigation')
        ->and($member->conflict_declared)->toBeFalse();

    $this->actingAs($users['investigator'])
        ->post(route('discipline.events.store', $case), [
            'event_type' => 'interview',
            'title' => 'Temu bual awal',
            'details' => 'Catatan sebelum deklarasi.',
            'occurred_at' => now()->subMinutes(50)->format('Y-m-d H:i:s'),
            'visible_to_complainant' => false,
            'visible_to_subject' => false,
        ])
        ->assertForbidden();
    $this->actingAs($users['investigator'])
        ->patch(route('discipline.members.conflict', [$case, $member]), [
            'has_conflict' => false,
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    $this->actingAs($users['investigator'])
        ->post(route('discipline.events.store', $case), [
            'event_type' => 'interview',
            'title' => 'Temu bual saksi pertama',
            'details' => 'Saksi mengesahkan urutan kejadian.',
            'occurred_at' => now()->subMinutes(30)->format('Y-m-d H:i:s'),
            'visible_to_complainant' => true,
            'visible_to_subject' => false,
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    $this->actingAs($users['investigator'])
        ->post(route('discipline.attachments.store', $case), [
            'attachment_context' => 'investigation',
            'attachment' => UploadedFile::fake()->image('foto-siasatan.jpg'),
            'visible_to_complainant' => false,
            'visible_to_subject' => false,
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    $this->actingAs($users['investigator'])
        ->patch(route('discipline.findings.submit', $case), [
            'finding_outcome' => 'substantiated',
            'finding_summary' => 'Bukti dokumen dan keterangan saksi adalah konsisten serta menyokong dakwaan.',
            'recommended_action' => 'Surat tunjuk sebab sebelum keputusan.',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    expect($case->fresh()->status)->toBe('show_cause_pending');

    $this->actingAs($users['hr'])
        ->patch(route('discipline.show-cause.issue', $case), [
            'due_at' => now()->addDays(9)->format('Y-m-d H:i:s'),
            'create_hr_document' => false,
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    $this->actingAs($users['subject'])
        ->post(route('employee-discipline.response', $case), [
            'statement' => 'Saya memberikan penjelasan lengkap tentang urutan kejadian dan menyertakan dokumen sokongan untuk pertimbangan.',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    expect($case->fresh()->status)->toBe('decision');

    $this->actingAs($users['decisionMaker'])
        ->patch(route('discipline.decision', $case), [
            'decision_outcome' => 'written_warning',
            'decision_notes' => 'Selepas meneliti dapatan dan representasi, amaran bertulis diputuskan secara berkadar.',
            'effective_date' => today()->addDays(10)->toDateString(),
            'create_hr_document' => false,
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    expect($case->fresh()->decision_outcome)->toBe('written_warning')
        ->and($case->fresh()->appeal_deadline)->not->toBeNull();

    $this->actingAs($users['subject'])
        ->post(route('employee-discipline.appeal', $case), [
            'grounds' => 'Saya memohon semakan kerana terdapat keadaan peringan dan dokumen tambahan yang belum dipertimbangkan.',
            'desired_outcome' => 'Tindakan dikurangkan kepada kaunseling.',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    $appeal = DisciplineAppeal::query()->firstOrFail();
    $this->actingAs($users['decisionMaker'])
        ->patch(route('discipline.appeals.review', [$case, $appeal]), [
            'outcome' => 'upheld',
            'decision_notes' => 'Cuba semak rayuan sendiri.',
        ])
        ->assertForbidden();
    $this->actingAs($users['appealReviewer'])
        ->patch(route('discipline.appeals.review', [$case, $appeal]), [
            'outcome' => 'varied',
            'decision_notes' => 'Keadaan peringan diterima dan tindakan diubah secara berkadar.',
            'revised_outcome' => 'counselling',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    expect($case->fresh()->status)->toBe('closed')
        ->and($case->fresh()->decision_outcome)->toBe('counselling')
        ->and(AuditLog::query()->where('action', 'discipline.complaint_submitted')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'discipline.appeal_decided')->exists())->toBeTrue();

    expect($legacyQueries)->not->toBeEmpty();
    foreach ($legacyQueries as $sql) {
        expect($sql)->toStartWith('select');
    }
});

test('protected identity and case details remain locked until conflict declaration', function () {
    $users = disciplineUsers();
    $case = submitDisciplineComplaint($users);
    $this->actingAs($users['hr'])
        ->patch(route('discipline.triage', $case), [
            'action' => 'accept',
            'severity' => 'high',
            'triage_notes' => 'Siasatan diperlukan.',
            'investigator_user_id' => $users['investigator']->getKey(),
            'allegation_summary' => 'Ringkasan dakwaan untuk siasatan.',
        ])
        ->assertRedirect();

    $this->actingAs($users['investigator'])
        ->get(route('discipline.index', ['case_id' => $case->getKey()]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('cases.data.0.title', 'Butiran dikunci sehingga deklarasi konflik')
            ->where('selectedCase.details_locked', true)
            ->where('selectedCase.title', 'Butiran dikunci sehingga deklarasi konflik')
            ->where('selectedCase.description', null)
            ->where('selectedCase.complainant_name', 'Dikunci sehingga deklarasi konflik'));
    $attachment = DisciplineAttachment::query()->firstOrFail();
    $this->actingAs($users['investigator'])
        ->get(route('discipline.attachments.download', [$case, $attachment]))
        ->assertForbidden();

    $member = DisciplineCaseMember::query()->firstOrFail();
    $this->actingAs($users['investigator'])
        ->patch(route('discipline.members.conflict', [$case, $member]), [
            'has_conflict' => false,
        ])
        ->assertRedirect();
    $this->actingAs($users['investigator'])
        ->get(route('discipline.index', ['case_id' => $case->getKey()]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectedCase.details_locked', false)
            ->where('selectedCase.complainant_name', 'Identiti Dilindungi'));
    $this->actingAs($users['investigator'])
        ->get(route('discipline.attachments.download', [$case, $attachment]))
        ->assertOk();
});

test('officer declaring a conflict is recused and loses case access', function () {
    $users = disciplineUsers();
    $case = submitDisciplineComplaint($users);
    $this->actingAs($users['hr'])
        ->patch(route('discipline.triage', $case), [
            'action' => 'accept',
            'severity' => 'high',
            'triage_notes' => 'Siasatan diperlukan.',
            'investigator_user_id' => $users['investigator']->getKey(),
            'allegation_summary' => 'Ringkasan dakwaan untuk siasatan.',
        ])
        ->assertRedirect();
    $member = DisciplineCaseMember::query()->firstOrFail();

    $this->actingAs($users['investigator'])
        ->patch(route('discipline.members.conflict', [$case, $member]), [
            'has_conflict' => true,
            'conflict_notes' => 'Saya mempunyai hubungan keluarga dengan subjek kes.',
        ])
        ->assertRedirect();

    expect($member->fresh()->recused_at)->not->toBeNull()
        ->and($case->fresh()->investigator_user_id)->toBeNull();
    $this->actingAs($users['investigator'])
        ->get(route('discipline.index', ['case_id' => $case->getKey()]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('cases.total', 0)
            ->where('selectedCase', null));
});

test('employees cannot download attachments from unrelated discipline cases', function () {
    $users = disciplineUsers();
    $case = submitDisciplineComplaint($users);
    $outsider = User::factory()->employee()->create();
    $attachment = DisciplineAttachment::query()->firstOrFail();

    $this->actingAs($outsider)
        ->get(route('employee-discipline.attachment', [$case, $attachment]))
        ->assertForbidden();
});
