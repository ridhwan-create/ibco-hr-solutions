<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('directory_id')->nullable()->unique();
            $table->foreignId('recruitment_candidate_id')
                ->unique()
                ->constrained('recruitment_candidates')
                ->restrictOnDelete();
            $table->foreignId('recruitment_offer_id')
                ->unique()
                ->constrained('recruitment_offers')
                ->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('employee_number', 30)->unique();
            $table->string('name');
            $table->string('identity_number', 40)->unique();
            $table->string('personal_email')->nullable();
            $table->string('official_email')->unique();
            $table->string('phone', 30)->nullable();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->string('position_name', 150);
            $table->string('employment_type', 30);
            $table->decimal('salary', 12, 2);
            $table->unsignedTinyInteger('probation_months')->default(0);
            $table->date('start_date')->index();
            $table->foreignId('manager_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('office_location_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('pending_activation')->index();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'start_date']);
        });

        Schema::table('employee_user_links', function (Blueprint $table) {
            $table->string('employee_source', 20)->default('legacy')->index();
            $table->foreignId('employee_record_id')
                ->nullable()
                ->unique()
                ->constrained('employee_records')
                ->nullOnDelete();
        });

        Schema::table('onboarding_cases', function (Blueprint $table) {
            $table->foreignId('employee_record_id')
                ->nullable()
                ->unique()
                ->constrained('employee_records')
                ->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('account_status', 30)->default('active')->index();
            $table->date('activation_date')->nullable()->index();
            $table->boolean('must_change_password')->default(false);
            $table->timestamp('credentials_issued_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'account_status',
                'activation_date',
                'must_change_password',
                'credentials_issued_at',
            ]);
        });

        Schema::table('onboarding_cases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_record_id');
        });

        Schema::table('employee_user_links', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_record_id');
            $table->dropColumn('employee_source');
        });

        Schema::dropIfExists('employee_records');
    }
};
