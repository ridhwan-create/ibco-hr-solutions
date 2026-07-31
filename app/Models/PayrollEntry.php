<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PayrollEntry extends Model
{
    protected $fillable = [
        'payroll_run_id',
        'employee_id',
        'employee_number',
        'employee_name',
        'basic_salary',
        'overtime_minutes',
        'overtime_amount',
        'claim_reimbursements',
        'unpaid_leave_days',
        'unpaid_leave_amount',
        'recurring_earnings',
        'recurring_deductions',
        'manual_earnings',
        'manual_deductions',
        'gross_pay',
        'total_deductions',
        'net_pay',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'basic_salary' => 'decimal:2',
            'overtime_minutes' => 'integer',
            'overtime_amount' => 'decimal:2',
            'claim_reimbursements' => 'decimal:2',
            'unpaid_leave_days' => 'decimal:1',
            'unpaid_leave_amount' => 'decimal:2',
            'recurring_earnings' => 'decimal:2',
            'recurring_deductions' => 'decimal:2',
            'manual_earnings' => 'decimal:2',
            'manual_deductions' => 'decimal:2',
            'gross_pay' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_pay' => 'decimal:2',
            'calculated_at' => 'datetime',
        ];
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollEntryItem::class);
    }

    public function statutorySnapshot(): HasOne
    {
        return $this->hasOne(PayrollStatutorySnapshot::class);
    }
}
