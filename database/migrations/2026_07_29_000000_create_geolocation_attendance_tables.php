<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('address', 500)->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('radius_meters')->default(100);
            $table->unsignedInteger('accuracy_limit_meters')->default(100);
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('employee_user_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('employee_id')->index();
            $table->foreignId('office_location_id')->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'is_active']);
        });

        Schema::create('geo_attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('employee_id')->index();
            $table->foreignId('office_location_id')->constrained()->restrictOnDelete();
            $table->date('attendance_date')->index();
            $table->dateTime('clock_in_at');
            $table->dateTime('clock_out_at')->nullable();
            $table->decimal('clock_in_latitude', 10, 7)->nullable();
            $table->decimal('clock_in_longitude', 10, 7)->nullable();
            $table->decimal('clock_in_accuracy_meters', 8, 2)->nullable();
            $table->decimal('clock_in_distance_meters', 8, 2)->nullable();
            $table->string('clock_in_ip', 45)->nullable();
            $table->text('clock_in_user_agent')->nullable();
            $table->decimal('clock_out_latitude', 10, 7)->nullable();
            $table->decimal('clock_out_longitude', 10, 7)->nullable();
            $table->decimal('clock_out_accuracy_meters', 8, 2)->nullable();
            $table->decimal('clock_out_distance_meters', 8, 2)->nullable();
            $table->string('clock_out_ip', 45)->nullable();
            $table->text('clock_out_user_agent')->nullable();
            $table->string('source', 24)->default('geolocation');
            $table->string('status', 24)->default('active')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'attendance_date']);
            $table->index(['office_location_id', 'attendance_date']);
        });

        Schema::create('attendance_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('geo_attendance_record_id')
                ->constrained('geo_attendance_records')
                ->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('employee_id')->index();
            $table->string('action', 32);
            $table->text('reason');
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_adjustments');
        Schema::dropIfExists('geo_attendance_records');
        Schema::dropIfExists('employee_user_links');
        Schema::dropIfExists('office_locations');
    }
};
