<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollComponent extends Model
{
    protected $fillable = [
        'code',
        'name',
        'type',
        'is_active',
        'is_epf_wage',
        'is_socso_wage',
        'is_eis_wage',
        'is_pcb_wage',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_epf_wage' => 'boolean',
            'is_socso_wage' => 'boolean',
            'is_eis_wage' => 'boolean',
            'is_pcb_wage' => 'boolean',
        ];
    }

    public function employeeComponents(): HasMany
    {
        return $this->hasMany(EmployeePayrollComponent::class);
    }
}
