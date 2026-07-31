<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePayrollComponent extends Model
{
    protected $fillable = [
        'employee_id',
        'payroll_component_id',
        'amount',
        'effective_from',
        'effective_to',
        'is_active',
        'notes',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'amount' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(PayrollComponent::class, 'payroll_component_id');
    }
}
