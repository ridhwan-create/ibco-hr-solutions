<?php

use App\Models\AuditLog;
use App\Models\DocumentSequence;
use App\Models\DocumentTemplate;
use App\Models\EmployeeUserLink;
use App\Models\HrDocument;
use App\Models\OfficeLocation;
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

test('complete HR document workflow keeps employee source database read only', function () {
    $employee = User::factory()->employee()->create([
        'name' => 'Nur Employee',
        'email' => 'employee@ibco.test',
    ]);
    $hr = User::factory()->hrAdmin()->create();
    $approver = User::factory()->hrManager()->create();
    EmployeeUserLink::query()->create([
        'user_id' => $employee->getKey(),
        'employee_id' => 100,
        'office_location_id' => OfficeLocation::query()->value('id'),
        'is_active' => true,
        'created_by' => $hr->getKey(),
        'updated_by' => $hr->getKey(),
    ]);
    DocumentSequence::query()->create([
        'sequence_key' => 'DEFAULT',
        'name' => 'Surat HR Umum',
        'prefix' => 'IBCO/HR',
        'format' => '{{PREFIX}}/{{YEAR}}/{{SEQ:05}}',
        'next_number' => 1,
        'reset_annually' => true,
        'is_active' => true,
        'created_by' => $hr->getKey(),
        'updated_by' => $hr->getKey(),
    ]);
    $template = DocumentTemplate::query()->create([
        'code' => 'EMP-CONFIRM',
        'name' => 'Pengesahan Jawatan',
        'category' => 'confirmation',
        'subject_template' => 'Pengesahan Jawatan – {{employee_name}}',
        'body_template' => 'Perkhidmatan {{employee_name}} sebagai {{position_name}} disahkan berkuat kuasa {{effective_date}}.',
        'available_variables' => ['employee_name', 'position_name', 'effective_date'],
        'sequence_key' => 'DEFAULT',
        'requires_approval' => true,
        'approver_user_id' => $approver->getKey(),
        'acknowledgement_required' => true,
        'confidentiality' => 'confidential',
        'is_active' => true,
        'created_by' => $hr->getKey(),
        'updated_by' => $hr->getKey(),
    ]);

    $legacyQueries = [];
    Event::listen(QueryExecuted::class, function (QueryExecuted $event) use (&$legacyQueries) {
        if ($event->connectionName === 'ibco') {
            $legacyQueries[] = strtolower(ltrim($event->sql));
        }
    });

    $this->actingAs($hr)
        ->post(route('hr-documents.store'), [
            'document_template_id' => $template->getKey(),
            'employee_user_id' => $employee->getKey(),
            'source_type' => 'onboarding',
            'source_id' => 25,
            'effective_date' => '2026-08-15',
            'signatory_name' => 'Pengurus Sumber Manusia',
            'signatory_position' => 'Ketua Jabatan HR',
            'approver_user_id' => $approver->getKey(),
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    $document = HrDocument::query()->firstOrFail();
    expect($document->status)->toBe('draft')
        ->and($document->employee_id)->toBe(100)
        ->and($document->employee_name)->toBe('Nur Employee')
        ->and($document->department_name)->toBe('Teknologi Maklumat')
        ->and($document->template_snapshot['code'])->toBe('EMP-CONFIRM');

    $template->update(['body_template' => 'Kandungan template baharu.']);
    expect($document->fresh()->body)->not->toBe('Kandungan template baharu.');

    $this->actingAs($hr)
        ->patch(route('hr-documents.submit', $document))
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    expect($document->fresh()->status)->toBe('pending_approval');
    expect(app(LeaveApprovalAlerts::class)
        ->summarizeFor($approver)['document_approval'])->toBe(1);

    $this->actingAs($approver)
        ->patch(route('hr-documents.review', $document), [
            'action' => 'approve',
            'notes' => 'Kandungan dan kuasa melulus disahkan.',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    expect($document->fresh()->status)->toBe('approved');

    $this->actingAs($hr)
        ->patch(route('hr-documents.issue', $document))
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    expect($document->fresh()->status)->toBe('issued')
        ->and($document->fresh()->reference_number)->toBe('IBCO/HR/2026/00001');
    expect(DocumentSequence::query()->firstOrFail()->next_number)->toBe(2);

    $this->actingAs($hr)
        ->post(route('hr-documents.attachments.store', $document), [
            'attachment_type' => 'signed_copy',
            'attachment' => UploadedFile::fake()->create(
                'surat-pengesahan-ditandatangani.pdf',
                120,
                'application/pdf',
            ),
            'visible_to_employee' => true,
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    $attachment = $document->attachments()->firstOrFail();

    $this->actingAs($employee)
        ->get(route('employee-documents.index'))
        ->assertOk();
    $this->actingAs($employee)
        ->get(route('employee-documents.pdf', $document))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
    $this->actingAs($employee)
        ->get(route('employee-documents.attachment', [$document, $attachment]))
        ->assertOk();
    $this->actingAs($employee)
        ->patch(route('employee-documents.acknowledge', $document), [
            'confirmed' => true,
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
    expect($document->fresh()->status)->toBe('acknowledged')
        ->and($document->fresh()->acknowledged_by)->toBe($employee->getKey())
        ->and(AuditLog::query()->where('action', 'hr_document.issued')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'hr_document.acknowledged')->exists())->toBeTrue();

    expect($legacyQueries)->not->toBeEmpty();
    foreach ($legacyQueries as $sql) {
        expect($sql)->toStartWith('select');
    }
});

test('employees cannot read another employees confidential document', function () {
    $employee = User::factory()->employee()->create();
    $otherEmployee = User::factory()->employee()->create();
    $document = HrDocument::query()->create([
        'reference_number' => 'IBCO/HR/2026/00999',
        'template_name' => 'Surat Sulit',
        'category' => 'warning',
        'employee_user_id' => $employee->getKey(),
        'employee_id' => 100,
        'employee_name' => 'Nur Employee',
        'subject' => 'Surat Sulit',
        'body' => 'Kandungan sulit.',
        'status' => 'issued',
        'approval_required' => true,
        'issued_at' => now(),
        'acknowledgement_required' => true,
        'confidentiality' => 'restricted',
    ]);

    $this->actingAs($otherEmployee)
        ->get(route('employee-documents.pdf', $document))
        ->assertForbidden();
});

test('hr cannot approve documents while the assigned approver can', function () {
    $hr = User::factory()->hrAdmin()->create();
    $approver = User::factory()->hrManager()->create();
    $document = HrDocument::query()->create([
        'template_name' => 'Surat Amaran',
        'category' => 'warning',
        'employee_id' => 100,
        'employee_name' => 'Nur Employee',
        'subject' => 'Surat Amaran',
        'body' => 'Kandungan surat.',
        'status' => 'pending_approval',
        'approval_required' => true,
        'approver_user_id' => $approver->getKey(),
        'acknowledgement_required' => true,
        'confidentiality' => 'restricted',
        'created_by' => $hr->getKey(),
    ]);

    $this->actingAs($hr)
        ->patch(route('hr-documents.review', $document), [
            'action' => 'approve',
        ])
        ->assertForbidden();
    $this->actingAs($approver)
        ->patch(route('hr-documents.review', $document), [
            'action' => 'approve',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    expect($document->fresh()->status)->toBe('approved');
});
