<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeSalaryProfile extends Model
{
    protected $fillable = [
        'employee_id',
        'basic_salary',
        'effective_from',
        'is_active',
        'notes',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'basic_salary' => 'decimal:2',
            'effective_from' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function recurringComponents(): HasMany
    {
        return $this->hasMany(
            EmployeePayrollComponent::class,
            'employee_id',
            'employee_id',
        );
    }
}
