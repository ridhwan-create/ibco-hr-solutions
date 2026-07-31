<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('break_minutes')->default(60);
            $table->unsignedSmallInteger('grace_minutes')->default(10);
            $table->unsignedSmallInteger('early_departure_grace_minutes')->default(5);
            $table->boolean('crosses_midnight')->default(false);
            $table->json('work_days');
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();

        DB::table('shift_templates')->insert([
            [
                'code' => 'OFFICE',
                'name' => 'Waktu Pejabat',
                'description' => 'Jadual standard Isnin hingga Jumaat.',
                'start_time' => '08:30:00',
                'end_time' => '17:30:00',
                'break_minutes' => 60,
                'grace_minutes' => 10,
                'early_departure_grace_minutes' => 5,
                'crosses_midnight' => false,
                'work_days' => json_encode([1, 2, 3, 4, 5]),
                'is_default' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'MORNING',
                'name' => 'Syif Pagi',
                'description' => 'Syif operasi pagi.',
                'start_time' => '07:00:00',
                'end_time' => '15:00:00',
                'break_minutes' => 45,
                'grace_minutes' => 10,
                'early_departure_grace_minutes' => 5,
                'crosses_midnight' => false,
                'work_days' => json_encode([1, 2, 3, 4, 5, 6]),
                'is_default' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'EVENING',
                'name' => 'Syif Petang',
                'description' => 'Syif operasi petang.',
                'start_time' => '15:00:00',
                'end_time' => '23:00:00',
                'break_minutes' => 45,
                'grace_minutes' => 10,
                'early_departure_grace_minutes' => 5,
                'crosses_midnight' => false,
                'work_days' => json_encode([1, 2, 3, 4, 5, 6]),
                'is_default' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'NIGHT',
                'name' => 'Syif Malam',
                'description' => 'Syif operasi yang merentas tengah malam.',
                'start_time' => '23:00:00',
                'end_time' => '07:00:00',
                'break_minutes' => 45,
                'grace_minutes' => 10,
                'early_departure_grace_minutes' => 5,
                'crosses_midnight' => true,
                'work_days' => json_encode([1, 2, 3, 4, 5, 6]),
                'is_default' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        Schema::create('schedule_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_template_id')
                ->constrained('shift_templates')
                ->restrictOnDelete();
            $table->string('scope_type', 24)->index();
            $table->unsignedBigInteger('employee_id')->nullable()->index();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->foreignId('office_location_id')
                ->nullable()
                ->constrained('office_locations')
                ->restrictOnDelete();
            $table->date('effective_from')->index();
            $table->date('effective_to')->nullable()->index();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(
                ['scope_type', 'effective_from', 'effective_to'],
                'schedule_scope_dates_idx',
            );
        });

        Schema::create('roster_periods', function (Blueprint $table) {
            $table->id();
            $table->date('period_start')->unique();
            $table->date('period_end');
            $table->string('status', 24)->default('draft')->index();
            $table->text('notes')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('roster_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('roster_period_id')
                ->constrained('roster_periods')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('employee_id')->index();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->foreignId('office_location_id')
                ->nullable()
                ->constrained('office_locations')
                ->nullOnDelete();
            $table->foreignId('shift_template_id')
                ->nullable()
                ->constrained('shift_templates')
                ->nullOnDelete();
            $table->date('work_date')->index();
            $table->string('day_type', 24)->default('workday')->index();
            $table->dateTime('scheduled_start_at')->nullable();
            $table->dateTime('scheduled_end_at')->nullable();
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->string('source', 24)->default('generated');
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['employee_id', 'work_date'],
                'roster_employee_date_uq',
            );
            $table->index(
                ['roster_period_id', 'department_id', 'office_location_id'],
                'roster_period_scope_idx',
            );
        });

        Schema::create('shift_swap_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_roster_entry_id')
                ->constrained('roster_entries')
                ->restrictOnDelete();
            $table->foreignId('target_roster_entry_id')
                ->constrained('roster_entries')
                ->restrictOnDelete();
            $table->foreignId('requester_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->text('reason');
            $table->string('status', 24)->default('pending')->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('roster_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('roster_period_id')
                ->nullable()
                ->constrained('roster_periods')
                ->cascadeOnDelete();
            $table->foreignId('shift_swap_request_id')
                ->nullable()
                ->constrained('shift_swap_requests')
                ->cascadeOnDelete();
            $table->string('title', 150);
            $table->text('message');
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::table('geo_attendance_records', function (Blueprint $table) {
            $table->foreignId('roster_entry_id')
                ->nullable()
                ->after('office_location_id')
                ->constrained('roster_entries')
                ->nullOnDelete();
            $table->dateTime('scheduled_start_at')->nullable()->after('attendance_date');
            $table->dateTime('scheduled_end_at')->nullable()->after('scheduled_start_at');
            $table->unsignedSmallInteger('scheduled_minutes')->default(0);
            $table->unsignedSmallInteger('late_minutes')->default(0)->index();
            $table->unsignedSmallInteger('early_departure_minutes')->default(0)->index();
            $table->string('attendance_day_type', 24)->nullable()->index();
        });

        Schema::table('overtime_requests', function (Blueprint $table) {
            $table->foreignId('roster_entry_id')
                ->nullable()
                ->after('attendance_record_id')
                ->constrained('roster_entries')
                ->nullOnDelete();
            $table->string('roster_day_type', 24)->nullable()->index();
            $table->string('roster_match_status', 32)->default('not_found')->index();
        });
    }

    public function down(): void
    {
        Schema::table('overtime_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('roster_entry_id');
            $table->dropColumn(['roster_day_type', 'roster_match_status']);
        });

        Schema::table('geo_attendance_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('roster_entry_id');
            $table->dropColumn([
                'scheduled_start_at',
                'scheduled_end_at',
                'scheduled_minutes',
                'late_minutes',
                'early_departure_minutes',
                'attendance_day_type',
            ]);
        });

        Schema::dropIfExists('roster_notifications');
        Schema::dropIfExists('shift_swap_requests');
        Schema::dropIfExists('roster_entries');
        Schema::dropIfExists('roster_periods');
        Schema::dropIfExists('schedule_assignments');
        Schema::dropIfExists('shift_templates');
    }
};
