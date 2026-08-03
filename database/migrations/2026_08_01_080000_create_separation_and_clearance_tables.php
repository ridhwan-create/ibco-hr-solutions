<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('separation_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
        });

        Schema::create('separation_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->string('separation_type', 40)->nullable()->index();
            $table->unsignedSmallInteger('minimum_notice_days')->default(30);
            $table->boolean('employee_can_apply')->default(false)->index();
            $table->boolean('exit_interview_required')->default(true);
            $table->boolean('final_settlement_required')->default(true);
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('clearance_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('separation_template_id')->constrained('separation_templates')->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('owner_type', 30)->default('hr')->index();
            $table->foreignId('assignee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->smallInteger('due_offset_days')->default(0);
            $table->boolean('is_mandatory')->default(true);
            $table->boolean('employee_action_required')->default(false);
            $table->boolean('evidence_required')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('separation_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_number', 80)->unique();
            $table->foreignId('separation_template_id')->nullable()->constrained('separation_templates')->nullOnDelete();
            $table->foreignId('employee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('employee_id')->index();
            $table->string('employee_number', 50)->nullable();
            $table->string('employee_name', 200);
            $table->string('employee_email', 190)->nullable();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->string('department_name', 180)->nullable();
            $table->string('position_name', 180)->nullable();
            $table->string('separation_type', 40)->index();
            $table->boolean('initiated_by_employee')->default(false);
            $table->string('reason_category', 80)->nullable();
            $table->longText('reason_details');
            $table->date('notice_submitted_date');
            $table->date('proposed_last_day');
            $table->date('approved_last_day')->nullable()->index();
            $table->unsignedSmallInteger('notice_days_required')->default(0);
            $table->unsignedSmallInteger('notice_days_served')->default(0);
            $table->unsignedSmallInteger('notice_shortfall_days')->default(0);
            $table->boolean('notice_waived')->default(false);
            $table->text('waiver_notes')->nullable();
            $table->string('status', 40)->default('draft')->index();
            $table->string('approval_stage', 30)->nullable()->index();
            $table->foreignId('supervisor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('supervisor_decision', 20)->nullable();
            $table->text('supervisor_notes')->nullable();
            $table->foreignId('supervisor_decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('supervisor_decided_at')->nullable();
            $table->foreignId('hr_approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('hr_decision', 20)->nullable();
            $table->text('hr_notes')->nullable();
            $table->foreignId('hr_decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('hr_decided_at')->nullable();
            $table->dateTime('clearance_started_at')->nullable();
            $table->date('clearance_due_date')->nullable()->index();
            $table->foreignId('acceptance_document_id')->nullable()->constrained('hr_documents')->nullOnDelete();
            $table->foreignId('clearance_document_id')->nullable()->constrained('hr_documents')->nullOnDelete();
            $table->boolean('eligible_for_rehire')->nullable();
            $table->text('closure_notes')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_user_id', 'status'], 'sep_employee_status_idx');
            $table->index(['supervisor_user_id', 'approval_stage'], 'sep_supervisor_stage_idx');
            $table->index(['hr_approver_user_id', 'approval_stage'], 'sep_hr_stage_idx');
        });

        Schema::create('clearance_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('separation_case_id')->constrained('separation_cases')->cascadeOnDelete();
            $table->foreignId('clearance_template_item_id')->nullable()->constrained('clearance_template_items')->nullOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('owner_type', 30)->index();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_mandatory')->default(true);
            $table->boolean('employee_action_required')->default(false);
            $table->boolean('evidence_required')->default(false);
            $table->date('due_date')->nullable()->index();
            $table->string('status', 30)->default('pending')->index();
            $table->text('submission_notes')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('submitted_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('waived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('waived_at')->nullable();
            $table->text('waiver_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['assigned_user_id', 'status'], 'clearance_assignee_status_idx');
        });

        Schema::create('separation_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('separation_case_id')->constrained('separation_cases')->cascadeOnDelete();
            $table->foreignId('clearance_task_id')->nullable()->constrained('clearance_tasks')->cascadeOnDelete();
            $table->string('context', 40)->default('supporting')->index();
            $table->string('disk', 32)->default('local');
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->boolean('visible_to_employee')->default(false)->index();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('separation_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('separation_case_id')->constrained('separation_cases')->cascadeOnDelete();
            $table->string('asset_type', 60);
            $table->string('asset_name', 180);
            $table->string('asset_tag', 100)->nullable();
            $table->string('serial_number', 120)->nullable();
            $table->date('issued_date')->nullable();
            $table->date('expected_return_date')->nullable()->index();
            $table->dateTime('returned_at')->nullable();
            $table->string('return_condition', 40)->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->decimal('estimated_value', 12, 2)->default(0);
            $table->decimal('charge_amount', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('verified_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('handover_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('separation_case_id')->constrained('separation_cases')->cascadeOnDelete();
            $table->string('title', 200);
            $table->longText('description');
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable()->index();
            $table->string('status', 30)->default('pending')->index();
            $table->text('submission_notes')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('submitted_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('exit_interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('separation_case_id')->unique()->constrained('separation_cases')->cascadeOnDelete();
            $table->foreignId('interviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('employee_submitted_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->string('primary_reason', 100)->nullable();
            $table->unsignedTinyInteger('employment_experience_rating')->nullable();
            $table->unsignedTinyInteger('manager_support_rating')->nullable();
            $table->boolean('would_recommend')->nullable();
            $table->longText('positive_feedback')->nullable();
            $table->longText('improvement_feedback')->nullable();
            $table->longText('additional_feedback')->nullable();
            $table->longText('hr_private_notes')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('final_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('separation_case_id')->unique()->constrained('separation_cases')->cascadeOnDelete();
            $table->decimal('salary_due', 12, 2)->default(0);
            $table->decimal('leave_encashment', 12, 2)->default(0);
            $table->decimal('gratuity', 12, 2)->default(0);
            $table->decimal('claims_due', 12, 2)->default(0);
            $table->decimal('other_payments', 12, 2)->default(0);
            $table->decimal('notice_deduction', 12, 2)->default(0);
            $table->decimal('asset_deduction', 12, 2)->default(0);
            $table->decimal('loan_deduction', 12, 2)->default(0);
            $table->decimal('other_deductions', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('prepared_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('separation_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('separation_case_id')->nullable()->constrained('separation_cases')->cascadeOnDelete();
            $table->string('type', 60)->index();
            $table->string('title', 180);
            $table->text('message');
            $table->dateTime('read_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('separation_notifications');
        Schema::dropIfExists('final_settlements');
        Schema::dropIfExists('exit_interviews');
        Schema::dropIfExists('handover_items');
        Schema::dropIfExists('separation_assets');
        Schema::dropIfExists('separation_attachments');
        Schema::dropIfExists('clearance_tasks');
        Schema::dropIfExists('separation_cases');
        Schema::dropIfExists('clearance_template_items');
        Schema::dropIfExists('separation_templates');
        Schema::dropIfExists('separation_sequences');
    }
};
