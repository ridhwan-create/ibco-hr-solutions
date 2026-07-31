<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OvertimeRequest extends Model
{
    public const STATUSES = ['pending', 'approved', 'rejected', 'cancelled'];

    protected $fillable = [
        'user_id',
        'employee_id',
        'department_id',
        'overtime_type_id',
        'attendance_record_id',
        'roster_entry_id',
        'roster_day_type',
        'roster_match_status',
        'work_date',
        'start_at',
        'end_at',
        'break_minutes',
        'requested_minutes',
        'approved_minutes',
        'attendance_match_status',
        'reason',
        'work_description',
        'status',
        'approval_stage',
        'submitted_at',
        'supervisor_reviewed_at',
        'supervisor_reviewed_by',
        'supervisor_review_notes',
        'reviewed_at',
        'reviewed_by',
        'review_notes',
        'attachment_disk',
        'attachment_path',
        'attachment_original_name',
        'attachment_mime_type',
        'attachment_size',
    ];

    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'department_id' => 'integer',
            'work_date' => 'date',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'break_minutes' => 'integer',
            'requested_minutes' => 'integer',
            'approved_minutes' => 'integer',
            'submitted_at' => 'datetime',
            'supervisor_reviewed_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'attachment_size' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function overtimeType(): BelongsTo
    {
        return $this->belongsTo(OvertimeType::class);
    }

    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(GeoAttendanceRecord::class, 'attendance_record_id');
    }

    public function rosterEntry(): BelongsTo
    {
        return $this->belongsTo(RosterEntry::class);
    }

    public function supervisorReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_reviewed_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(OvertimeNotification::class);
    }
}
