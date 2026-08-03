<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('sequence_key', 40)->unique();
            $table->string('name', 120);
            $table->string('prefix', 50)->default('IBCO/HR');
            $table->string('format', 150)->default('{{PREFIX}}/{{YEAR}}/{{SEQ:05}}');
            $table->unsignedBigInteger('next_number')->default(1);
            $table->unsignedSmallInteger('last_year')->nullable();
            $table->boolean('reset_annually')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 180);
            $table->string('category', 50)->index();
            $table->string('subject_template', 500);
            $table->longText('body_template');
            $table->json('available_variables')->nullable();
            $table->string('sequence_key', 40)->default('DEFAULT')->index();
            $table->boolean('requires_approval')->default(true);
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('acknowledgement_required')->default(false);
            $table->unsignedSmallInteger('default_validity_months')->nullable();
            $table->string('confidentiality', 24)->default('confidential');
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('hr_documents', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number', 120)->nullable()->unique();
            $table->foreignId('document_template_id')->nullable()->constrained('document_templates')->nullOnDelete();
            $table->string('template_code', 40)->nullable();
            $table->string('template_name', 180);
            $table->string('category', 50)->index();
            $table->foreignId('employee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('employee_id')->index();
            $table->string('employee_number', 50)->nullable();
            $table->string('employee_name', 200);
            $table->string('employee_email', 190)->nullable();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->string('department_name', 180)->nullable();
            $table->string('position_name', 180)->nullable();
            $table->string('source_type', 40)->default('manual')->index();
            $table->unsignedBigInteger('source_id')->nullable()->index();
            $table->string('subject', 500);
            $table->longText('body');
            $table->json('template_snapshot')->nullable();
            $table->json('custom_variables')->nullable();
            $table->string('signatory_name', 180)->nullable();
            $table->string('signatory_position', 180)->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->boolean('approval_required')->default(true);
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->date('effective_date')->nullable();
            $table->date('expiry_date')->nullable()->index();
            $table->boolean('acknowledgement_required')->default(false);
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->string('acknowledgement_ip', 45)->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->foreignId('supersedes_document_id')->nullable()->constrained('hr_documents')->nullOnDelete();
            $table->string('confidentiality', 24)->default('confidential');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(
                ['employee_user_id', 'status'],
                'hr_document_employee_status_idx',
            );
            $table->index(
                ['approver_user_id', 'status'],
                'hr_document_approver_status_idx',
            );
        });

        Schema::create('hr_document_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_document_id')->constrained('hr_documents')->cascadeOnDelete();
            $table->string('attachment_type', 32);
            $table->string('disk', 32)->default('local');
            $table->string('path', 500);
            $table->string('original_name');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->boolean('visible_to_employee')->default(false)->index();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('hr_document_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('hr_document_id')->nullable()->constrained('hr_documents')->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('title', 180);
            $table->text('message');
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();

            $table->index(
                ['user_id', 'read_at'],
                'hr_document_notification_user_read_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_document_notifications');
        Schema::dropIfExists('hr_document_attachments');
        Schema::dropIfExists('hr_documents');
        Schema::dropIfExists('document_templates');
        Schema::dropIfExists('document_sequences');
    }
};
