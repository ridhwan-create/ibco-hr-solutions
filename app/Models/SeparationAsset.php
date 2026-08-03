<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeparationAsset extends Model
{
    public const STATUSES = ['pending', 'returned', 'damaged', 'lost', 'waived'];

    protected $fillable = [
        'separation_case_id', 'asset_type', 'asset_name', 'asset_tag',
        'serial_number', 'issued_date', 'expected_return_date', 'returned_at',
        'return_condition', 'status', 'estimated_value', 'charge_amount',
        'notes', 'verified_by', 'verified_at', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'issued_date' => 'date',
            'expected_return_date' => 'date',
            'returned_at' => 'datetime',
            'estimated_value' => 'decimal:2',
            'charge_amount' => 'decimal:2',
            'verified_at' => 'datetime',
        ];
    }

    public function separationCase(): BelongsTo
    {
        return $this->belongsTo(SeparationCase::class);
    }
}
