<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClaimType extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'max_per_claim',
        'monthly_limit',
        'annual_limit',
        'requires_receipt',
        'requires_receipt_number',
        'allow_payroll_reimbursement',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'max_per_claim' => 'decimal:2',
            'monthly_limit' => 'decimal:2',
            'annual_limit' => 'decimal:2',
            'requires_receipt' => 'boolean',
            'requires_receipt_number' => 'boolean',
            'allow_payroll_reimbursement' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ClaimRequest::class);
    }

    public function limitOverrides(): HasMany
    {
        return $this->hasMany(ClaimLimitOverride::class);
    }
}
