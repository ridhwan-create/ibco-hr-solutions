<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RosterEntry extends Model
{
    public const DAY_TYPES = [
        'workday',
        'rest_day',
        'public_holiday',
        'off',
    ];

    protected $fillable = [
        'roster_period_id',
        'user_id',
        'employee_id',
        'department_id',
        'office_location_id',
        'shift_template_id',
        'work_date',
        'day_type',
        'scheduled_start_at',
        'scheduled_end_at',
        'break_minutes',
        'source',
        'notes',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'department_id' => 'integer',
            'user_id' => 'integer',
            'office_location_id' => 'integer',
            'shift_template_id' => 'integer',
            'work_date' => 'immutable_date',
            'scheduled_start_at' => 'immutable_datetime',
            'scheduled_end_at' => 'immutable_datetime',
            'break_minutes' => 'integer',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(RosterPeriod::class, 'roster_period_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function officeLocation(): BelongsTo
    {
        return $this->belongsTo(OfficeLocation::class);
    }

    public function shiftTemplate(): BelongsTo
    {
        return $this->belongsTo(ShiftTemplate::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(GeoAttendanceRecord::class);
    }
}
