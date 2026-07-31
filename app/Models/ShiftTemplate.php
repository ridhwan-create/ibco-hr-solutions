<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShiftTemplate extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'start_time',
        'end_time',
        'break_minutes',
        'grace_minutes',
        'early_departure_grace_minutes',
        'crosses_midnight',
        'work_days',
        'is_default',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'break_minutes' => 'integer',
            'grace_minutes' => 'integer',
            'early_departure_grace_minutes' => 'integer',
            'crosses_midnight' => 'boolean',
            'work_days' => 'array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ScheduleAssignment::class);
    }

    public function rosterEntries(): HasMany
    {
        return $this->hasMany(RosterEntry::class);
    }
}
