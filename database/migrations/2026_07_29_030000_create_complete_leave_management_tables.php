<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 150);
            $table->decimal('default_entitlement_days', 6, 1)->default(0);
            $table->boolean('deduct_balance')->default(true);
            $table->boolean('allow_half_day')->default(false);
            $table->boolean('requires_attachment')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();

        DB::table('leave_types')->insert([
            [
                'code' => 'ANNUAL',
                'name' => 'Cuti Tahunan',
                'default_entitlement_days' => 20,
                'deduct_balance' => true,
                'allow_half_day' => true,
                'requires_attachment' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'SICK',
                'name' => 'Cuti Sakit',
                'default_entitlement_days' => 14,
                'deduct_balance' => true,
                'allow_half_day' => false,
                'requires_attachment' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'EMERGENCY',
                'name' => 'Cuti Kecemasan',
                'default_entitlement_days' => 0,
                'deduct_balance' => true,
                'allow_half_day' => true,
                'requires_attachment' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'UNPAID',
                'name' => 'Cuti Tanpa Gaji',
                'default_entitlement_days' => 0,
                'deduct_balance' => false,
                'allow_half_day' => true,
                'requires_attachment' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'OTHER',
                'name' => 'Cuti Lain-lain',
                'default_entitlement_days' => 0,
                'deduct_balance' => false,
                'allow_half_day' => false,
                'requires_attachment' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        Schema::create('leave_entitlements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->index();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year')->index();
            $table->decimal('entitled_days', 6, 1)->default(0);
            $table->decimal('carry_forward_days', 6, 1)->default(0);
            $table->decimal('adjustment_days', 6, 1)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'leave_type_id', 'year'], 'leave_entitlements_unique');
        });

        Schema::create('leave_approval_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('department_id')->unique();
            $table->foreignId('approver_user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('public_holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->date('holiday_date')->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('employee_leave_requests', function (Blueprint $table) {
            $table->decimal('requested_days', 5, 1)->change();
            $table->foreignId('system_leave_type_id')
                ->nullable()
                ->after('leave_type_id')
                ->constrained('leave_types')
                ->nullOnDelete();
            $table->unsignedBigInteger('department_id')->nullable()->after('employee_id')->index();
            $table->string('duration_type', 24)->default('full_day')->after('end_date');
            $table->string('approval_stage', 24)->default('hr')->after('status')->index();
            $table->timestamp('supervisor_reviewed_at')->nullable()->after('submitted_at');
            $table->foreignId('supervisor_reviewed_by')
                ->nullable()
                ->after('supervisor_reviewed_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->text('supervisor_review_notes')->nullable()->after('supervisor_reviewed_by');
            $table->string('attachment_disk', 32)->nullable();
            $table->string('attachment_path', 500)->nullable();
            $table->string('attachment_original_name')->nullable();
            $table->string('attachment_mime_type', 100)->nullable();
            $table->unsignedBigInteger('attachment_size')->nullable();
        });

        DB::table('employee_leave_requests')
            ->whereIn('status', ['approved', 'rejected', 'cancelled'])
            ->update(['approval_stage' => 'completed']);

        Schema::create('leave_balance_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_entitlement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_request_id')
                ->nullable()
                ->constrained('employee_leave_requests')
                ->nullOnDelete();
            $table->string('transaction_type', 32);
            $table->decimal('days', 6, 1);
            $table->text('notes')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['leave_request_id', 'transaction_type'],
                'leave_balance_request_type_unique',
            );
        });

        Schema::create('leave_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_request_id')
                ->nullable()
                ->constrained('employee_leave_requests')
                ->cascadeOnDelete();
            $table->string('title', 150);
            $table->text('message');
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_notifications');
        Schema::dropIfExists('leave_balance_transactions');

        Schema::table('employee_leave_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('system_leave_type_id');
            $table->dropConstrainedForeignId('supervisor_reviewed_by');
            $table->dropColumn([
                'department_id',
                'duration_type',
                'approval_stage',
                'supervisor_reviewed_at',
                'supervisor_review_notes',
                'attachment_disk',
                'attachment_path',
                'attachment_original_name',
                'attachment_mime_type',
                'attachment_size',
            ]);
            $table->unsignedSmallInteger('requested_days')->change();
        });

        Schema::dropIfExists('public_holidays');
        Schema::dropIfExists('leave_approval_assignments');
        Schema::dropIfExists('leave_entitlements');
        Schema::dropIfExists('leave_types');
    }
};
