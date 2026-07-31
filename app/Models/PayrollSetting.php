<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollSetting extends Model
{
    protected $fillable = [
        'currency',
        'working_days_divisor',
        'daily_hours',
        'include_approved_overtime',
        'deduct_unpaid_leave',
        'company_name',
        'company_registration_no',
        'company_address',
        'payslip_note',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'working_days_divisor' => 'decimal:2',
            'daily_hours' => 'decimal:2',
            'include_approved_overtime' => 'boolean',
            'deduct_unpaid_leave' => 'boolean',
        ];
    }
}
