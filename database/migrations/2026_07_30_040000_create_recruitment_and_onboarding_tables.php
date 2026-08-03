<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_requisitions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('title', 150);
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->string('position_name', 150)->nullable()->index();
            $table->string('employment_type', 24);
            $table->unsignedSmallInteger('vacancies')->default(1);
            $table->foreignId('hiring_manager_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('location', 150)->nullable();
            $table->text('description');
            $table->text('requirements');
            $table->decimal('min_salary', 12, 2)->nullable();
            $table->decimal('max_salary', 12, 2)->nullable();
            $table->date('target_hire_date')->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->text('approval_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(
                ['department_id', 'status'],
                'recruitment_req_department_status_idx',
            );
        });

        Schema::create('recruitment_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recruitment_requisition_id')
                ->constrained('recruitment_requisitions')
                ->cascadeOnDelete();
            $table->string('candidate_number', 32)->unique();
            $table->string('name', 150);
            $table->string('email', 190);
            $table->string('phone', 40);
            $table->string('nric', 30)->nullable();
            $table->string('current_company', 150)->nullable();
            $table->string('current_position', 150)->nullable();
            $table->decimal('expected_salary', 12, 2)->nullable();
            $table->unsignedSmallInteger('notice_period_days')->nullable();
            $table->string('source', 50)->default('direct');
            $table->string('stage', 24)->default('applied')->index();
            $table->decimal('rating', 3, 2)->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('screening_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('withdrawal_reason')->nullable();
            $table->timestamp('applied_at');
            $table->timestamp('hired_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['recruitment_requisition_id', 'email'],
                'recruitment_candidate_req_email_unique',
            );
            $table->index(
                ['recruitment_requisition_id', 'stage'],
                'recruitment_candidate_req_stage_idx',
            );
        });

        Schema::create('recruitment_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recruitment_candidate_id')
                ->constrained('recruitment_candidates')
                ->cascadeOnDelete();
            $table->string('document_type', 32);
            $table->string('disk', 32)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('recruitment_interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recruitment_candidate_id')
                ->constrained('recruitment_candidates')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('round')->default(1);
            $table->string('interview_type', 32);
            $table->dateTime('scheduled_at');
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->string('location_or_link', 500)->nullable();
            $table->json('panel_user_ids');
            $table->string('status', 24)->default('scheduled')->index();
            $table->decimal('overall_score', 3, 2)->nullable();
            $table->string('overall_recommendation', 24)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(
                ['scheduled_at', 'status'],
                'recruitment_interview_schedule_status_idx',
            );
        });

        Schema::create('recruitment_scorecards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recruitment_interview_id')
                ->constrained('recruitment_interviews')
                ->cascadeOnDelete();
            $table->foreignId('panel_user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('technical_score', 3, 2);
            $table->decimal('communication_score', 3, 2);
            $table->decimal('culture_score', 3, 2);
            $table->decimal('overall_score', 3, 2);
            $table->string('recommendation', 24);
            $table->text('strengths');
            $table->text('concerns')->nullable();
            $table->text('comments')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->unique(
                ['recruitment_interview_id', 'panel_user_id'],
                'recruitment_scorecard_panel_unique',
            );
        });

        Schema::create('recruitment_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recruitment_candidate_id')
                ->constrained('recruitment_candidates')
                ->cascadeOnDelete();
            $table->string('offer_number', 32)->unique();
            $table->string('position_name', 150);
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->string('employment_type', 24);
            $table->decimal('salary', 12, 2);
            $table->date('start_date');
            $table->unsignedSmallInteger('probation_months')->default(3);
            $table->date('expiry_date');
            $table->text('terms')->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->text('approval_notes')->nullable();
            $table->text('response_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('onboarding_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 150);
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->string('position_name', 150)->nullable()->index();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('onboarding_template_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onboarding_template_id')
                ->constrained('onboarding_templates')
                ->cascadeOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('category', 24);
            $table->string('assignee_role', 24);
            $table->integer('due_offset_days')->default(0);
            $table->boolean('is_required')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestamps();
        });

        Schema::create('onboarding_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recruitment_candidate_id')
                ->unique()
                ->constrained('recruitment_candidates')
                ->cascadeOnDelete();
            $table->foreignId('recruitment_offer_id')
                ->nullable()
                ->constrained('recruitment_offers')
                ->nullOnDelete();
            $table->foreignId('onboarding_template_id')
                ->nullable()
                ->constrained('onboarding_templates')
                ->nullOnDelete();
            $table->unsignedBigInteger('legacy_employee_id')->nullable()->index();
            $table->foreignId('employee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('manager_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('buddy_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_date');
            $table->string('status', 24)->default('pending')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('onboarding_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onboarding_case_id')
                ->constrained('onboarding_cases')
                ->cascadeOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('category', 24);
            $table->string('assignee_role', 24);
            $table->foreignId('assignee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date');
            $table->boolean('is_required')->default(true);
            $table->string('status', 24)->default('pending')->index();
            $table->text('completion_notes')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestamps();

            $table->index(
                ['assignee_user_id', 'status', 'due_date'],
                'onboarding_task_assignee_status_due_idx',
            );
        });

        Schema::create('recruitment_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recruitment_candidate_id')
                ->nullable()
                ->constrained('recruitment_candidates')
                ->cascadeOnDelete();
            $table->foreignId('recruitment_requisition_id')
                ->nullable()
                ->constrained('recruitment_requisitions')
                ->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('title', 180);
            $table->text('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(
                ['user_id', 'read_at'],
                'recruitment_notification_user_read_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_notifications');
        Schema::dropIfExists('onboarding_tasks');
        Schema::dropIfExists('onboarding_cases');
        Schema::dropIfExists('onboarding_template_tasks');
        Schema::dropIfExists('onboarding_templates');
        Schema::dropIfExists('recruitment_offers');
        Schema::dropIfExists('recruitment_scorecards');
        Schema::dropIfExists('recruitment_interviews');
        Schema::dropIfExists('recruitment_documents');
        Schema::dropIfExists('recruitment_candidates');
        Schema::dropIfExists('recruitment_requisitions');
    }
};
