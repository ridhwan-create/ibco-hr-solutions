<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GeoAttendanceRecord extends Model
{
    protected $fillable = [
        'user_id',
        'employee_id',
        'office_location_id',
        'roster_entry_id',
        'attendance_date',
        'scheduled_start_at',
        'scheduled_end_at',
        'scheduled_minutes',
        'late_minutes',
        'early_departure_minutes',
        'attendance_day_type',
        'clock_in_at',
        'clock_out_at',
        'clock_in_latitude',
        'clock_in_longitude',
        'clock_in_accuracy_meters',
        'clock_in_distance_meters',
        'clock_in_ip',
        'clock_in_user_agent',
        'clock_out_latitude',
        'clock_out_longitude',
        'clock_out_accuracy_meters',
        'clock_out_distance_meters',
        'clock_out_ip',
        'clock_out_user_agent',
        'source',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'attendance_date' => 'date',
            'scheduled_start_at' => 'datetime',
            'scheduled_end_at' => 'datetime',
            'scheduled_minutes' => 'integer',
            'late_minutes' => 'integer',
            'early_departure_minutes' => 'integer',
            'clock_in_at' => 'datetime',
            'clock_out_at' => 'datetime',
            'clock_in_latitude' => 'decimal:7',
            'clock_in_longitude' => 'decimal:7',
            'clock_in_accuracy_meters' => 'decimal:2',
            'clock_in_distance_meters' => 'decimal:2',
            'clock_out_latitude' => 'decimal:7',
            'clock_out_longitude' => 'decimal:7',
            'clock_out_accuracy_meters' => 'decimal:2',
            'clock_out_distance_meters' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function officeLocation(): BelongsTo
    {
        return $this->belongsTo(OfficeLocation::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(AttendanceAdjustment::class);
    }

    public function rosterEntry(): BelongsTo
    {
        return $this->belongsTo(RosterEntry::class);
    }
}
