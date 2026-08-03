<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaint_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('default_severity', 20)->default('medium');
            $table->unsignedSmallInteger('sla_days')->default(30);
            $table->unsignedSmallInteger('appeal_days')->default(14);
            $table->boolean('requires_show_cause')->default(true);
            $table->boolean('allow_protected_identity')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('discipline_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_number', 80)->unique();
            $table->foreignId('complaint_category_id')->constrained('complaint_categories')->restrictOnDelete();
            $table->foreignId('complainant_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('complainant_employee_id')->nullable()->index();
            $table->string('complainant_employee_number', 40)->nullable();
            $table->string('complainant_name', 180)->nullable();
            $table->string('complainant_email', 180)->nullable();
            $table->unsignedBigInteger('complainant_department_id')->nullable()->index();
            $table->string('complainant_department_name', 180)->nullable();
            $table->boolean('identity_protected')->default(true);
            $table->foreignId('subject_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('subject_employee_id')->nullable()->index();
            $table->string('subject_employee_number', 40)->nullable();
            $table->string('subject_name', 180)->nullable();
            $table->string('subject_email', 180)->nullable();
            $table->unsignedBigInteger('subject_department_id')->nullable()->index();
            $table->string('subject_department_name', 180)->nullable();
            $table->string('subject_position_name', 180)->nullable();
            $table->string('title', 240);
            $table->dateTime('incident_at')->nullable();
            $table->string('incident_location', 255)->nullable();
            $table->longText('description');
            $table->text('requested_resolution')->nullable();
            $table->string('severity', 20)->default('medium')->index();
            $table->string('confidentiality', 20)->default('restricted');
            $table->string('status', 40)->default('submitted')->index();
            $table->text('triage_notes')->nullable();
            $table->foreignId('triaged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('triaged_at')->nullable();
            $table->foreignId('investigator_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('investigation_started_at')->nullable();
            $table->date('target_completion_date')->nullable()->index();
            $table->longText('allegation_summary')->nullable();
            $table->string('finding_outcome', 40)->nullable();
            $table->longText('finding_summary')->nullable();
            $table->longText('recommended_action')->nullable();
            $table->foreignId('finding_submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('finding_submitted_at')->nullable();
            $table->dateTime('show_cause_due_at')->nullable();
            $table->string('decision_outcome', 40)->nullable();
            $table->longText('decision_notes')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->date('effective_date')->nullable();
            $table->date('appeal_deadline')->nullable();
            $table->foreignId('hr_document_id')->nullable()->constrained('hr_documents')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('closed_at')->nullable();
            $table->text('closure_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['subject_user_id', 'status'], 'dc_subject_status_idx');
            $table->index(['complainant_user_id', 'status'], 'dc_complainant_status_idx');
        });

        Schema::create('discipline_case_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discipline_case_id')->constrained('discipline_cases')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 30)->default('investigator');
            $table->boolean('conflict_declared')->default(false);
            $table->boolean('has_conflict')->nullable();
            $table->text('conflict_notes')->nullable();
            $table->dateTime('conflict_declared_at')->nullable();
            $table->dateTime('recused_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['discipline_case_id', 'user_id'], 'dcm_case_user_uq');
        });

        Schema::create('discipline_case_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discipline_case_id')->constrained('discipline_cases')->cascadeOnDelete();
            $table->string('event_type', 40)->index();
            $table->string('title', 220);
            $table->longText('details')->nullable();
            $table->dateTime('occurred_at');
            $table->boolean('visible_to_complainant')->default(false);
            $table->boolean('visible_to_subject')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('discipline_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discipline_case_id')->constrained('discipline_cases')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('attachment_context', 40)->default('complaint')->index();
            $table->string('original_name', 255);
            $table->string('disk', 40)->default('local');
            $table->string('path', 500);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->boolean('visible_to_complainant')->default(false);
            $table->boolean('visible_to_subject')->default(false);
            $table->timestamps();
        });

        Schema::create('discipline_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discipline_case_id')->constrained('discipline_cases')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('response_type', 40)->default('show_cause');
            $table->longText('statement');
            $table->boolean('is_confidential')->default(true);
            $table->dateTime('submitted_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['discipline_case_id', 'user_id', 'response_type'], 'dr_case_user_type_uq');
        });

        Schema::create('discipline_appeals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discipline_case_id')->constrained('discipline_cases')->cascadeOnDelete();
            $table->foreignId('appellant_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->longText('grounds');
            $table->text('desired_outcome')->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->longText('decision_notes')->nullable();
            $table->string('revised_outcome', 40)->nullable();
            $table->timestamps();
            $table->unique(['discipline_case_id', 'appellant_user_id'], 'da_case_appellant_uq');
        });

        Schema::create('discipline_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('discipline_case_id')->nullable()->constrained('discipline_cases')->cascadeOnDelete();
            $table->string('type', 60)->index();
            $table->string('title', 180);
            $table->text('message');
            $table->dateTime('read_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_notifications');
        Schema::dropIfExists('discipline_appeals');
        Schema::dropIfExists('discipline_responses');
        Schema::dropIfExists('discipline_attachments');
        Schema::dropIfExists('discipline_case_events');
        Schema::dropIfExists('discipline_case_members');
        Schema::dropIfExists('discipline_cases');
        Schema::dropIfExists('complaint_categories');
    }
};
