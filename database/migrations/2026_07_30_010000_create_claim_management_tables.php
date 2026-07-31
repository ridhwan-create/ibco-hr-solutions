<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claim_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->decimal('max_per_claim', 12, 2)->nullable();
            $table->decimal('monthly_limit', 12, 2)->nullable();
            $table->decimal('annual_limit', 12, 2)->nullable();
            $table->boolean('requires_receipt')->default(true);
            $table->boolean('requires_receipt_number')->default(false);
            $table->boolean('allow_payroll_reimbursement')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();

        DB::table('claim_types')->insert([
            $this->claimType('TRAVEL', 'Perjalanan', 500, 1000, 6000, true, false, $now),
            $this->claimType('PETROL', 'Petrol', 300, 600, 3600, true, true, $now),
            $this->claimType('TOLL', 'Tol', 200, 400, 2400, true, true, $now),
            $this->claimType('PARKING', 'Parkir', 100, 250, 1500, true, true, $now),
            $this->claimType('MEAL', 'Makan', 150, 400, 2400, true, true, $now),
            $this->claimType('MEDICAL', 'Perubatan', 500, 1000, 5000, true, true, $now),
            $this->claimType('OTHER', 'Lain-lain', 500, 1000, 5000, true, true, $now),
        ]);

        Schema::create('claim_limit_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_type_id')->constrained()->cascadeOnDelete();
            $table->string('scope_type', 16);
            $table->unsignedBigInteger('scope_id');
            $table->decimal('max_per_claim', 12, 2)->nullable();
            $table->decimal('monthly_limit', 12, 2)->nullable();
            $table->decimal('annual_limit', 12, 2)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['claim_type_id', 'scope_type', 'scope_id'],
                'claim_limit_scope_unique',
            );
        });

        Schema::create('claim_approval_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('department_id')->unique();
            $table->foreignId('approver_user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('claim_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('employee_id')->index();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->unsignedBigInteger('position_id')->nullable()->index();
            $table->foreignId('claim_type_id')->constrained()->restrictOnDelete();
            $table->date('expense_date')->index();
            $table->string('merchant_name', 150)->nullable();
            $table->string('receipt_number', 100)->nullable();
            $table->char('receipt_fingerprint', 64)->nullable()->unique();
            $table->decimal('requested_amount', 12, 2);
            $table->decimal('approved_amount', 12, 2)->nullable();
            $table->text('description');
            $table->string('status', 24)->default('pending')->index();
            $table->string('approval_stage', 24)->default('finance')->index();
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
            $table->date('scheduled_payroll_period')->nullable()->index();
            $table->foreignId('payroll_run_id')
                ->nullable()
                ->constrained('payroll_runs')
                ->nullOnDelete();
            $table->timestamp('paid_at')->nullable()->index();
            $table->timestamps();

            $table->index(['employee_id', 'expense_date', 'status']);
        });

        Schema::create('claim_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_request_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 32)->default('local');
            $table->string('path', 500);
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('claim_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('claim_request_id')
                ->nullable()
                ->constrained()
                ->cascadeOnDelete();
            $table->string('title', 150);
            $table->text('message');
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::table('payroll_entries', function (Blueprint $table) {
            $table->decimal('claim_reimbursements', 12, 2)
                ->default(0)
                ->after('overtime_amount');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_entries', function (Blueprint $table) {
            $table->dropColumn('claim_reimbursements');
        });
        Schema::dropIfExists('claim_notifications');
        Schema::dropIfExists('claim_attachments');
        Schema::dropIfExists('claim_requests');
        Schema::dropIfExists('claim_approval_assignments');
        Schema::dropIfExists('claim_limit_overrides');
        Schema::dropIfExists('claim_types');
    }

    /**
     * @return array<string, mixed>
     */
    private function claimType(
        string $code,
        string $name,
        float $maxPerClaim,
        float $monthlyLimit,
        float $annualLimit,
        bool $requiresReceipt,
        bool $requiresReceiptNumber,
        mixed $now,
    ): array {
        return [
            'code' => $code,
            'name' => $name,
            'max_per_claim' => $maxPerClaim,
            'monthly_limit' => $monthlyLimit,
            'annual_limit' => $annualLimit,
            'requires_receipt' => $requiresReceipt,
            'requires_receipt_number' => $requiresReceiptNumber,
            'allow_payroll_reimbursement' => true,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
};
