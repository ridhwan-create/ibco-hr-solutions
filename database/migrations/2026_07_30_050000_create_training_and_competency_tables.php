<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_providers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 180);
            $table->string('contact_person', 150)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('accreditation', 180)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('training_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_provider_id')->nullable()->constrained('training_providers')->nullOnDelete();
            $table->string('code', 32)->unique();
            $table->string('title', 200);
            $table->string('category', 60)->index();
            $table->string('delivery_method', 24);
            $table->text('description')->nullable();
            $table->text('learning_objectives')->nullable();
            $table->decimal('duration_hours', 7, 2)->default(0);
            $table->decimal('cpd_points', 7, 2)->default(0);
            $table->decimal('default_cost', 12, 2)->default(0);
            $table->string('currency', 3)->default('MYR');
            $table->unsignedSmallInteger('certificate_validity_months')->nullable();
            $table->boolean('is_mandatory')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_course_id')->constrained('training_courses')->cascadeOnDelete();
            $table->string('session_code', 40)->unique();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->date('registration_deadline')->nullable();
            $table->string('venue', 250)->nullable();
            $table->string('facilitator', 180)->nullable();
            $table->unsignedSmallInteger('capacity')->default(1);
            $table->decimal('cost_per_participant', 12, 2)->default(0);
            $table->string('budget_code', 50)->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['starts_at', 'status'], 'training_session_start_status_idx');
        });

        Schema::create('training_budgets', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('budget_code', 50)->nullable();
            $table->decimal('allocated_amount', 14, 2);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['year', 'department_id'], 'training_budget_year_department_unique');
        });

        Schema::create('training_approval_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('department_id')->unique();
            $table->foreignId('approver_user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('competencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 180);
            $table->string('category', 60)->index();
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('maximum_level')->default(5);
            $table->json('level_descriptions')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('competency_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competency_id')->constrained('competencies')->cascadeOnDelete();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->string('position_name', 150)->nullable()->index();
            $table->unsignedTinyInteger('required_level');
            $table->boolean('is_mandatory')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['competency_id', 'department_id', 'position_name'],
                'competency_requirement_scope_unique',
            );
        });

        Schema::create('employee_competencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('employee_id')->index();
            $table->foreignId('competency_id')->constrained('competencies')->cascadeOnDelete();
            $table->unsignedTinyInteger('current_level');
            $table->string('assessment_source', 32)->default('manager');
            $table->text('evidence_notes')->nullable();
            $table->foreignId('assessed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assessed_at');
            $table->timestamps();

            $table->unique(
                ['employee_user_id', 'competency_id'],
                'employee_competency_user_skill_unique',
            );
        });

        Schema::create('development_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('employee_id')->index();
            $table->foreignId('competency_id')->nullable()->constrained('competencies')->nullOnDelete();
            $table->unsignedBigInteger('performance_review_id')->nullable()->index();
            $table->unsignedBigInteger('performance_improvement_plan_id')->nullable()->index();
            $table->string('source', 24)->default('competency_gap');
            $table->string('title', 200);
            $table->text('action_plan');
            $table->unsignedTinyInteger('target_level')->nullable();
            $table->date('due_date');
            $table->string('status', 24)->default('planned')->index();
            $table->text('completion_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('performance_review_id', 'development_plan_review_fk')
                ->references('id')->on('performance_reviews')->nullOnDelete();
            $table->foreign('performance_improvement_plan_id', 'development_plan_pip_fk')
                ->references('id')->on('performance_improvement_plans')->nullOnDelete();
        });

        Schema::create('training_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number', 40)->unique();
            $table->foreignId('employee_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('employee_id')->index();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->unsignedSmallInteger('budget_year')->index();
            $table->string('position_name', 150)->nullable();
            $table->foreignId('training_session_id')->nullable()->constrained('training_sessions')->nullOnDelete();
            $table->foreignId('development_plan_id')->nullable()->constrained('development_plans')->nullOnDelete();
            $table->string('course_title', 200);
            $table->text('justification');
            $table->string('development_source', 24)->default('self');
            $table->decimal('estimated_cost', 12, 2)->default(0);
            $table->decimal('approved_cost', 12, 2)->nullable();
            $table->string('status', 24)->default('pending')->index();
            $table->string('approval_stage', 24)->nullable()->index();
            $table->foreignId('supervisor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('supervisor_notes')->nullable();
            $table->timestamp('supervisor_reviewed_at')->nullable();
            $table->foreignId('hr_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('hr_notes')->nullable();
            $table->timestamp('hr_reviewed_at')->nullable();
            $table->string('attendance_status', 24)->default('not_recorded')->index();
            $table->decimal('attended_hours', 7, 2)->nullable();
            $table->decimal('assessment_score', 6, 2)->nullable();
            $table->boolean('passed')->nullable();
            $table->unsignedTinyInteger('employee_rating')->nullable();
            $table->text('employee_feedback')->nullable();
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(
                ['employee_user_id', 'status'],
                'training_request_employee_status_idx',
            );
            $table->index(
                ['department_id', 'approval_stage', 'status'],
                'training_request_department_approval_idx',
            );
        });

        Schema::create('training_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_request_id')->constrained('training_requests')->cascadeOnDelete();
            $table->string('attachment_type', 24);
            $table->string('disk', 32)->default('local');
            $table->string('path', 500);
            $table->string('original_name');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->date('valid_until')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('training_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('training_request_id')->nullable()->constrained('training_requests')->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('title', 180);
            $table->text('message');
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'read_at'], 'training_notification_user_read_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_notifications');
        Schema::dropIfExists('training_attachments');
        Schema::dropIfExists('training_requests');
        Schema::dropIfExists('development_plans');
        Schema::dropIfExists('employee_competencies');
        Schema::dropIfExists('competency_requirements');
        Schema::dropIfExists('competencies');
        Schema::dropIfExists('training_approval_assignments');
        Schema::dropIfExists('training_budgets');
        Schema::dropIfExists('training_sessions');
        Schema::dropIfExists('training_courses');
        Schema::dropIfExists('training_providers');
    }
};
