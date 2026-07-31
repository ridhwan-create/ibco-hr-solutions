<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollEntryItem extends Model
{
    protected $fillable = [
        'payroll_entry_id',
        'payroll_component_id',
        'code',
        'name',
        'type',
        'category',
        'quantity',
        'rate',
        'multiplier',
        'amount',
        'source_type',
        'source_id',
        'is_manual',
        'is_epf_wage',
        'is_socso_wage',
        'is_eis_wage',
        'is_pcb_wage',
        'notes',
        'added_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'rate' => 'decimal:4',
            'multiplier' => 'decimal:2',
            'amount' => 'decimal:2',
            'source_id' => 'integer',
            'is_manual' => 'boolean',
            'is_epf_wage' => 'boolean',
            'is_socso_wage' => 'boolean',
            'is_eis_wage' => 'boolean',
            'is_pcb_wage' => 'boolean',
        ];
    }

    public function payrollEntry(): BelongsTo
    {
        return $this->belongsTo(PayrollEntry::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(PayrollComponent::class, 'payroll_component_id');
    }
}
