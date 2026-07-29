<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeLeaveRequest extends Model
{
    public const STATUSES = [
        'pending',
        'approved',
        'rejected',
        'cancelled',
    ];

    protected $fillable = [
        'user_id',
        'employee_id',
        'department_id',
        'leave_type_id',
        'system_leave_type_id',
        'leave_type_label',
        'start_date',
        'end_date',
        'duration_type',
        'requested_days',
        'reason',
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
            'leave_type_id' => 'integer',
            'system_leave_type_id' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'requested_days' => 'decimal:1',
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

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function supervisorReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_reviewed_by');
    }

    public function systemLeaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'system_leave_type_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(LeaveNotification::class, 'leave_request_id');
    }
}
