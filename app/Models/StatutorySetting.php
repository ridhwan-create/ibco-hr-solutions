<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatutorySetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'kwsp_effective_from' => 'date',
            'socso_effective_from' => 'date',
            'eis_effective_from' => 'date',
            'kwsp_table_limit' => 'decimal:2',
            'kwsp_employer_threshold' => 'decimal:2',
            'kwsp_employee_rate' => 'decimal:3',
            'kwsp_employer_rate_low' => 'decimal:3',
            'kwsp_employer_rate_high' => 'decimal:3',
            'kwsp_age60_employee_rate' => 'decimal:3',
            'kwsp_age60_employer_rate' => 'decimal:3',
            'kwsp_pr_age60_employee_rate' => 'decimal:3',
            'kwsp_pr_age60_employer_rate' => 'decimal:3',
            'kwsp_foreign_employee_rate' => 'decimal:3',
            'kwsp_foreign_employer_rate' => 'decimal:3',
            'socso_wage_ceiling' => 'decimal:2',
            'socso_first_employer_rate' => 'decimal:3',
            'socso_first_employee_rate' => 'decimal:3',
            'socso_skbbk_employee_rate' => 'decimal:3',
            'socso_second_employer_rate' => 'decimal:3',
            'eis_wage_ceiling' => 'decimal:2',
            'eis_employee_rate' => 'decimal:3',
            'eis_employer_rate' => 'decimal:3',
            'pcb_tax_year' => 'integer',
        ];
    }
}
