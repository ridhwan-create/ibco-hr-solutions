<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 150);
            $table->decimal('rate_multiplier', 4, 2)->default(1.50);
            $table->unsignedSmallInteger('minimum_minutes')->default(30);
            $table->decimal('maximum_hours', 4, 1)->default(12);
            $table->boolean('requires_attachment')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();

        DB::table('overtime_types')->insert([
            [
                'code' => 'WEEKDAY',
                'name' => 'Hari Bekerja Biasa',
                'rate_multiplier' => 1.50,
                'minimum_minutes' => 30,
                'maximum_hours' => 4,
                'requires_attachment' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'REST_DAY',
                'name' => 'Hari Rehat',
                'rate_multiplier' => 2.00,
                'minimum_minutes' => 30,
                'maximum_hours' => 12,
                'requires_attachment' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'PUBLIC_HOLIDAY',
                'name' => 'Cuti Umum',
                'rate_multiplier' => 3.00,
                'minimum_minutes' => 30,
                'maximum_hours' => 12,
                'requires_attachment' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'OTHER',
                'name' => 'Lain-lain',
                'rate_multiplier' => 1.00,
                'minimum_minutes' => 30,
                'maximum_hours' => 12,
                'requires_attachment' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        Schema::create('overtime_approval_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('department_id')->unique();
            $table->foreignId('approver_user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('employee_id')->index();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->foreignId('overtime_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('attendance_record_id')
                ->nullable()
                ->constrained('geo_attendance_records')
                ->nullOnDelete();
            $table->date('work_date')->index();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->unsignedSmallInteger('requested_minutes');
            $table->unsignedSmallInteger('approved_minutes')->nullable();
            $table->string('attendance_match_status', 32)->default('not_found');
            $table->text('reason');
            $table->text('work_description');
            $table->string('status', 24)->default('pending')->index();
            $table->string('approval_stage', 24)->default('hr')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('supervisor_reviewed_at')->nullable();
            $table->foreignId('supervisor_reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('supervisor_review_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable();
            $table->string('attachment_disk', 32)->nullable();
            $table->string('attachment_path', 500)->nullable();
            $table->string('attachment_original_name')->nullable();
            $table->string('attachment_mime_type', 100)->nullable();
            $table->unsignedBigInteger('attachment_size')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'work_date', 'status']);
        });

        Schema::create('overtime_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('overtime_request_id')
                ->nullable()
                ->constrained('overtime_requests')
                ->cascadeOnDelete();
            $table->string('title', 150);
            $table->text('message');
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_notifications');
        Schema::dropIfExists('overtime_requests');
        Schema::dropIfExists('overtime_approval_assignments');
        Schema::dropIfExists('overtime_types');
    }
};
