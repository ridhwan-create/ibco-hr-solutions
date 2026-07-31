<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->cleanUpInterruptedMigration();

        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->string('company_name')->default('IBCO Solutions');
            $table->string('company_registration_no', 80)->nullable();
            $table->text('company_address')->nullable();
            $table->string('payslip_note', 255)
                ->default('Dokumen ini dijana oleh sistem dan tidak memerlukan tandatangan.');
        });

        Schema::create('statutory_settings', function (Blueprint $table) {
            $table->id();
            $table->date('kwsp_effective_from')->default('2025-10-01');
            $table->decimal('kwsp_table_limit', 12, 2)->default(20000);
            $table->decimal('kwsp_employer_threshold', 12, 2)->default(5000);
            $table->decimal('kwsp_employee_rate', 6, 3)->default(11);
            $table->decimal('kwsp_employer_rate_low', 6, 3)->default(13);
            $table->decimal('kwsp_employer_rate_high', 6, 3)->default(12);
            $table->decimal('kwsp_age60_employee_rate', 6, 3)->default(0);
            $table->decimal('kwsp_age60_employer_rate', 6, 3)->default(4);
            $table->decimal('kwsp_pr_age60_employee_rate', 6, 3)->default(5.5);
            $table->decimal('kwsp_pr_age60_employer_rate', 6, 3)->default(6.5);
            $table->decimal('kwsp_foreign_employee_rate', 6, 3)->default(2);
            $table->decimal('kwsp_foreign_employer_rate', 6, 3)->default(2);
            $table->date('socso_effective_from')->default('2026-06-01');
            $table->decimal('socso_wage_ceiling', 12, 2)->default(6000);
            $table->decimal('socso_first_employer_rate', 6, 3)->default(1.75);
            $table->decimal('socso_first_employee_rate', 6, 3)->default(0.5);
            $table->decimal('socso_skbbk_employee_rate', 6, 3)->default(0.75);
            $table->decimal('socso_second_employer_rate', 6, 3)->default(1.25);
            $table->date('eis_effective_from')->default('2024-10-01');
            $table->decimal('eis_wage_ceiling', 12, 2)->default(6000);
            $table->decimal('eis_employee_rate', 6, 3)->default(0.2);
            $table->decimal('eis_employer_rate', 6, 3)->default(0.2);
            $table->unsignedSmallInteger('pcb_tax_year')->default(2026);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('statutory_settings')->insert([
            'kwsp_effective_from' => '2025-10-01',
            'kwsp_table_limit' => 20000,
            'kwsp_employer_threshold' => 5000,
            'kwsp_employee_rate' => 11,
            'kwsp_employer_rate_low' => 13,
            'kwsp_employer_rate_high' => 12,
            'kwsp_age60_employee_rate' => 0,
            'kwsp_age60_employer_rate' => 4,
            'kwsp_pr_age60_employee_rate' => 5.5,
            'kwsp_pr_age60_employer_rate' => 6.5,
            'kwsp_foreign_employee_rate' => 2,
            'kwsp_foreign_employer_rate' => 2,
            'socso_effective_from' => '2026-06-01',
            'socso_wage_ceiling' => 6000,
            'socso_first_employer_rate' => 1.75,
            'socso_first_employee_rate' => 0.5,
            'socso_skbbk_employee_rate' => 0.75,
            'socso_second_employer_rate' => 1.25,
            'eis_effective_from' => '2024-10-01',
            'eis_wage_ceiling' => 6000,
            'eis_employee_rate' => 0.2,
            'eis_employer_rate' => 0.2,
            'pcb_tax_year' => 2026,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('employee_statutory_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->unique();
            $table->string('kwsp_category', 32)->default('citizen_below_60');
            $table->string('socso_category', 16)->default('first');
            $table->boolean('eis_enabled')->default(true);
            $table->string('pcb_method', 16)->default('fixed');
            $table->decimal('pcb_monthly_amount', 12, 2)->default(0);
            $table->string('epf_number', 80)->nullable();
            $table->string('socso_number', 80)->nullable();
            $table->string('tax_number', 80)->nullable();
            $table->date('effective_from');
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('payroll_components', function (Blueprint $table) {
            $table->boolean('is_epf_wage')->default(false);
            $table->boolean('is_socso_wage')->default(false);
            $table->boolean('is_eis_wage')->default(false);
            $table->boolean('is_pcb_wage')->default(false);
        });

        DB::table('payroll_components')
            ->where('type', 'earning')
            ->update([
                'is_epf_wage' => true,
                'is_socso_wage' => true,
                'is_eis_wage' => true,
                'is_pcb_wage' => true,
            ]);

        Schema::table('payroll_entry_items', function (Blueprint $table) {
            $table->boolean('is_epf_wage')->default(false)->index();
            $table->boolean('is_socso_wage')->default(false)->index();
            $table->boolean('is_eis_wage')->default(false)->index();
            $table->boolean('is_pcb_wage')->default(false)->index();
        });

        Schema::create('payroll_statutory_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_entry_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('employee_statutory_profile_id')
                ->nullable();
            $table->foreign(
                'employee_statutory_profile_id',
                'payroll_stat_profile_fk',
            )
                ->references('id')
                ->on('employee_statutory_profiles')
                ->nullOnDelete();
            $table->string('kwsp_category', 32);
            $table->string('socso_category', 16);
            $table->boolean('eis_enabled');
            $table->decimal('epf_wages', 12, 2)->default(0);
            $table->decimal('socso_wages', 12, 2)->default(0);
            $table->decimal('eis_wages', 12, 2)->default(0);
            $table->decimal('pcb_wages', 12, 2)->default(0);
            $table->decimal('kwsp_employee', 12, 2)->default(0);
            $table->decimal('kwsp_employer', 12, 2)->default(0);
            $table->decimal('socso_employee', 12, 2)->default(0);
            $table->decimal('socso_employer', 12, 2)->default(0);
            $table->decimal('eis_employee', 12, 2)->default(0);
            $table->decimal('eis_employer', 12, 2)->default(0);
            $table->decimal('pcb', 12, 2)->default(0);
            $table->decimal('total_employee_deductions', 12, 2)->default(0);
            $table->decimal('total_employer_contributions', 12, 2)->default(0);
            $table->string('epf_number', 80)->nullable();
            $table->string('socso_number', 80)->nullable();
            $table->string('tax_number', 80)->nullable();
            $table->string('rate_version', 120);
            $table->json('calculation_details')->nullable();
            $table->boolean('is_overridden')->default(false)->index();
            $table->text('override_notes')->nullable();
            $table->foreignId('overridden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('calculated_at');
            $table->timestamps();
        });

        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->decimal('total_employee_statutory', 14, 2)->default(0);
            $table->decimal('total_employer_statutory', 14, 2)->default(0);
            $table->decimal('total_pcb', 14, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropColumn([
                'total_employee_statutory',
                'total_employer_statutory',
                'total_pcb',
            ]);
        });
        Schema::dropIfExists('payroll_statutory_snapshots');
        Schema::table('payroll_entry_items', function (Blueprint $table) {
            $table->dropColumn([
                'is_epf_wage',
                'is_socso_wage',
                'is_eis_wage',
                'is_pcb_wage',
            ]);
        });
        Schema::table('payroll_components', function (Blueprint $table) {
            $table->dropColumn([
                'is_epf_wage',
                'is_socso_wage',
                'is_eis_wage',
                'is_pcb_wage',
            ]);
        });
        Schema::dropIfExists('employee_statutory_profiles');
        Schema::dropIfExists('statutory_settings');
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->dropColumn([
                'company_name',
                'company_registration_no',
                'company_address',
                'payslip_note',
            ]);
        });
    }

    /**
     * MySQL commits DDL statements individually. If this migration previously
     * failed, remove only this migration's partial schema before retrying it.
     */
    private function cleanUpInterruptedMigration(): void
    {
        Schema::dropIfExists('payroll_statutory_snapshots');
        Schema::dropIfExists('employee_statutory_profiles');
        Schema::dropIfExists('statutory_settings');

        $this->dropColumnsIfPresent('payroll_runs', [
            'total_employee_statutory',
            'total_employer_statutory',
            'total_pcb',
        ]);
        $this->dropColumnsIfPresent('payroll_entry_items', [
            'is_epf_wage',
            'is_socso_wage',
            'is_eis_wage',
            'is_pcb_wage',
        ]);
        $this->dropColumnsIfPresent('payroll_components', [
            'is_epf_wage',
            'is_socso_wage',
            'is_eis_wage',
            'is_pcb_wage',
        ]);
        $this->dropColumnsIfPresent('payroll_settings', [
            'company_name',
            'company_registration_no',
            'company_address',
            'payslip_note',
        ]);
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function dropColumnsIfPresent(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $existingColumns = array_values(array_filter(
            $columns,
            fn (string $column): bool => Schema::hasColumn($table, $column),
        ));

        if ($existingColumns === []) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($existingColumns) {
            $table->dropColumn($existingColumns);
        });
    }
};
