<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimLimitOverride extends Model
{
    protected $fillable = [
        'claim_type_id',
        'scope_type',
        'scope_id',
        'max_per_claim',
        'monthly_limit',
        'annual_limit',
        'is_active',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'scope_id' => 'integer',
            'max_per_claim' => 'decimal:2',
            'monthly_limit' => 'decimal:2',
            'annual_limit' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function claimType(): BelongsTo
    {
        return $this->belongsTo(ClaimType::class);
    }
}
