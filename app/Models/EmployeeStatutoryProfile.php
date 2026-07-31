<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeStatutoryProfile extends Model
{
    public const KWSP_CATEGORIES = [
        'citizen_below_60',
        'citizen_60_plus',
        'pr_below_60',
        'pr_60_plus',
        'non_malaysian',
        'exempt',
    ];

    public const SOCSO_CATEGORIES = ['first', 'second', 'exempt'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'eis_enabled' => 'boolean',
            'pcb_monthly_amount' => 'decimal:2',
            'effective_from' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(PayrollStatutorySnapshot::class);
    }
}
