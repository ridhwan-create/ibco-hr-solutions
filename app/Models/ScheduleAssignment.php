<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleAssignment extends Model
{
    public const SCOPES = ['employee', 'department', 'office'];

    protected $fillable = [
        'shift_template_id',
        'scope_type',
        'employee_id',
        'department_id',
        'office_location_id',
        'effective_from',
        'effective_to',
        'priority',
        'is_active',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'department_id' => 'integer',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function shiftTemplate(): BelongsTo
    {
        return $this->belongsTo(ShiftTemplate::class);
    }

    public function officeLocation(): BelongsTo
    {
        return $this->belongsTo(OfficeLocation::class);
    }
}
