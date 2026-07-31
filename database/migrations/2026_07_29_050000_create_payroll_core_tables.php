<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_settings', function (Blueprint $table) {
            $table->id();
            $table->string('currency', 3)->default('MYR');
            $table->decimal('working_days_divisor', 5, 2)->default(26);
            $table->decimal('daily_hours', 4, 2)->default(8);
            $table->boolean('include_approved_overtime')->default(true);
            $table->boolean('deduct_unpaid_leave')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('payroll_settings')->insert([
            'currency' => 'MYR',
            'working_days_divisor' => 26,
            'daily_hours' => 8,
            'include_approved_overtime' => true,
            'deduct_unpaid_leave' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('payroll_components', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 150);
            $table->string('type', 16)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();

        DB::table('payroll_components')->insert([
            [
                'code' => 'FIXED_ALLOWANCE',
                'name' => 'Elaun Tetap',
                'type' => 'earning',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'FIXED_DEDUCTION',
                'name' => 'Potongan Tetap Lain-lain',
                'type' => 'deduction',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        Schema::create('employee_salary_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->unique();
            $table->decimal('basic_salary', 12, 2);
            $table->date('effective_from');
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('employee_payroll_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->index();
            $table->foreignId('payroll_component_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['employee_id', 'payroll_component_id'],
                'employee_payroll_component_unique',
            );
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->date('period_start')->unique();
            $table->string('status', 24)->default('draft')->index();
            $table->string('currency', 3)->default('MYR');
            $table->unsignedInteger('employee_count')->default(0);
            $table->decimal('total_basic_salary', 14, 2)->default(0);
            $table->decimal('total_earnings', 14, 2)->default(0);
            $table->decimal('total_deductions', 14, 2)->default(0);
            $table->decimal('total_net_pay', 14, 2)->default(0);
            $table->timestamp('generated_at')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('approval_notes')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('returned_to_draft_at')->nullable();
            $table->foreignId('returned_to_draft_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('return_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('employee_id')->index();
            $table->string('employee_number', 30)->nullable();
            $table->string('employee_name');
            $table->decimal('basic_salary', 12, 2)->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->decimal('overtime_amount', 12, 2)->default(0);
            $table->decimal('unpaid_leave_days', 6, 1)->default(0);
            $table->decimal('unpaid_leave_amount', 12, 2)->default(0);
            $table->decimal('recurring_earnings', 12, 2)->default(0);
            $table->decimal('recurring_deductions', 12, 2)->default(0);
            $table->decimal('manual_earnings', 12, 2)->default(0);
            $table->decimal('manual_deductions', 12, 2)->default(0);
            $table->decimal('gross_pay', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('net_pay', 12, 2)->default(0);
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id']);
        });

        Schema::create('payroll_entry_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_component_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('code', 64);
            $table->string('name', 150);
            $table->string('type', 16)->index();
            $table->string('category', 32)->index();
            $table->decimal('quantity', 12, 3)->nullable();
            $table->decimal('rate', 12, 4)->nullable();
            $table->decimal('multiplier', 6, 2)->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('source_type', 64)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->boolean('is_manual')->default(false)->index();
            $table->text('notes')->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_entry_items');
        Schema::dropIfExists('payroll_entries');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('employee_payroll_components');
        Schema::dropIfExists('employee_salary_profiles');
        Schema::dropIfExists('payroll_components');
        Schema::dropIfExists('payroll_settings');
    }
};
