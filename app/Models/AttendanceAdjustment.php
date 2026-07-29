<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceAdjustment extends Model
{
    protected $fillable = [
        'geo_attendance_record_id',
        'user_id',
        'employee_id',
        'action',
        'reason',
        'before_values',
        'after_values',
    ];

    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'before_values' => 'array',
            'after_values' => 'array',
        ];
    }

    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(GeoAttendanceRecord::class, 'geo_attendance_record_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
