<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OvertimeType extends Model
{
    protected $fillable = [
        'code',
        'name',
        'rate_multiplier',
        'minimum_minutes',
        'maximum_hours',
        'requires_attachment',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'rate_multiplier' => 'decimal:2',
            'minimum_minutes' => 'integer',
            'maximum_hours' => 'decimal:1',
            'requires_attachment' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
