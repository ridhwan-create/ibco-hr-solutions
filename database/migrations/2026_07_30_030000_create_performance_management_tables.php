<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 150);
            $table->string('cycle_type', 24);
            $table->date('period_start');
            $table->date('period_end');
            $table->date('self_assessment_due_at');
            $table->date('supervisor_due_at');
            $table->date('moderation_due_at');
            $table->string('status', 24)->default('draft')->index();
            $table->json('rating_scale');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->index(['period_start', 'period_end']);
        });

        Schema::create('performance_templates', function (Blueprint $table) {
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

        Schema::create('performance_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_template_id')
                ->constrained('performance_templates')
                ->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('measure_type', 24);
            $table->decimal('target_value', 12, 2)->nullable();
            $table->string('unit', 50)->nullable();
            $table->decimal('weight', 5, 2);
            $table->text('scoring_guide')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestamps();
        });

        Schema::create('performance_supervisor_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('department_id')->unique();
            $table->foreignId('supervisor_user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('performance_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_cycle_id')
                ->constrained('performance_cycles')
                ->cascadeOnDelete();
            $table->foreignId('performance_template_id')
                ->nullable()
                ->constrained('performance_templates')
                ->nullOnDelete();
            $table->unsignedBigInteger('employee_id')->index();
            $table->foreignId('employee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('supervisor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->string('position_name', 150)->nullable();
            $table->string('status', 32)->default('goal_setting')->index();
            $table->decimal('total_weight', 5, 2)->default(100);
            $table->decimal('self_score', 5, 2)->nullable();
            $table->decimal('supervisor_score', 5, 2)->nullable();
            $table->decimal('moderated_score', 5, 2)->nullable();
            $table->string('final_rating', 80)->nullable();
            $table->text('employee_summary')->nullable();
            $table->text('supervisor_summary')->nullable();
            $table->text('strengths')->nullable();
            $table->text('improvement_areas')->nullable();
            $table->text('development_plan')->nullable();
            $table->text('hr_comments')->nullable();
            $table->timestamp('self_submitted_at')->nullable();
            $table->timestamp('supervisor_submitted_at')->nullable();
            $table->timestamp('moderated_at')->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['performance_cycle_id', 'employee_id'],
                'performance_cycle_employee_unique',
            );
            $table->index(['performance_cycle_id', 'department_id', 'status'], 'performance_review_filter_idx');
        });

        Schema::create('performance_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_review_id')
                ->constrained('performance_reviews')
                ->cascadeOnDelete();
            $table->foreignId('performance_template_item_id')
                ->nullable()
                ->constrained('performance_template_items')
                ->nullOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('measure_type', 24);
            $table->decimal('target_value', 12, 2)->nullable();
            $table->string('unit', 50)->nullable();
            $table->decimal('weight', 5, 2);
            $table->text('scoring_guide')->nullable();
            $table->text('actual_achievement')->nullable();
            $table->decimal('self_score', 4, 2)->nullable();
            $table->text('self_comments')->nullable();
            $table->decimal('supervisor_score', 4, 2)->nullable();
            $table->text('supervisor_comments')->nullable();
            $table->decimal('moderated_score', 4, 2)->nullable();
            $table->text('moderation_comments')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestamps();
        });

        Schema::create('performance_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_review_id')
                ->constrained('performance_reviews')
                ->cascadeOnDelete();
            $table->foreignId('performance_goal_id')
                ->nullable()
                ->constrained('performance_goals')
                ->cascadeOnDelete();
            $table->string('disk', 32)->default('local');
            $table->string('path', 500);
            $table->string('original_name');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->text('description')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('performance_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('performance_review_id')
                ->nullable()
                ->constrained('performance_reviews')
                ->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('title', 150);
            $table->text('message');
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('performance_improvement_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('performance_review_id')->unique();
            $table->unsignedBigInteger('employee_id')->index();
            $table->foreignId('supervisor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('draft')->index();
            $table->date('start_date');
            $table->date('end_date');
            $table->text('reason');
            $table->text('objectives');
            $table->text('required_actions');
            $table->text('support_required')->nullable();
            $table->text('success_criteria');
            $table->text('outcome')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('performance_review_id', 'performance_pip_review_fk')
                ->references('id')
                ->on('performance_reviews')
                ->cascadeOnDelete();
        });

        Schema::create('performance_pip_checkins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('performance_improvement_plan_id');
            $table->date('checkin_date');
            $table->string('progress_status', 24);
            $table->text('progress_notes');
            $table->text('next_actions')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('performance_improvement_plan_id', 'performance_pip_checkin_fk')
                ->references('id')
                ->on('performance_improvement_plans')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_pip_checkins');
        Schema::dropIfExists('performance_improvement_plans');
        Schema::dropIfExists('performance_notifications');
        Schema::dropIfExists('performance_evidence');
        Schema::dropIfExists('performance_goals');
        Schema::dropIfExists('performance_reviews');
        Schema::dropIfExists('performance_supervisor_assignments');
        Schema::dropIfExists('performance_template_items');
        Schema::dropIfExists('performance_templates');
        Schema::dropIfExists('performance_cycles');
    }
};
