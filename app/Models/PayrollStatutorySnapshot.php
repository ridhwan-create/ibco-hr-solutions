<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollStatutorySnapshot extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'eis_enabled' => 'boolean',
            'epf_wages' => 'decimal:2',
            'socso_wages' => 'decimal:2',
            'eis_wages' => 'decimal:2',
            'pcb_wages' => 'decimal:2',
            'kwsp_employee' => 'decimal:2',
            'kwsp_employer' => 'decimal:2',
            'socso_employee' => 'decimal:2',
            'socso_employer' => 'decimal:2',
            'eis_employee' => 'decimal:2',
            'eis_employer' => 'decimal:2',
            'pcb' => 'decimal:2',
            'total_employee_deductions' => 'decimal:2',
            'total_employer_contributions' => 'decimal:2',
            'calculation_details' => 'array',
            'is_overridden' => 'boolean',
            'calculated_at' => 'datetime',
        ];
    }

    public function payrollEntry(): BelongsTo
    {
        return $this->belongsTo(PayrollEntry::class);
    }

    public function employeeStatutoryProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeStatutoryProfile::class);
    }
}
