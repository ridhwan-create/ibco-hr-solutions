<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingRequest extends Model
{
    public const STATUSES = ['pending', 'approved', 'rejected', 'cancelled', 'completed'];

    protected $fillable = [
        'request_number', 'employee_user_id', 'employee_id', 'department_id',
        'budget_year', 'position_name', 'training_session_id', 'development_plan_id',
        'course_title', 'justification', 'development_source', 'estimated_cost',
        'approved_cost', 'status', 'approval_stage', 'supervisor_user_id',
        'supervisor_notes', 'supervisor_reviewed_at', 'hr_user_id', 'hr_notes',
        'hr_reviewed_at', 'attendance_status', 'attended_hours',
        'assessment_score', 'passed', 'employee_rating', 'employee_feedback',
        'evaluated_at', 'completed_at', 'cancelled_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'employee_id' => 'integer', 'department_id' => 'integer',
            'budget_year' => 'integer',
            'estimated_cost' => 'decimal:2', 'approved_cost' => 'decimal:2',
            'attended_hours' => 'decimal:2', 'assessment_score' => 'decimal:2',
            'passed' => 'boolean', 'employee_rating' => 'integer',
            'supervisor_reviewed_at' => 'datetime', 'hr_reviewed_at' => 'datetime',
            'evaluated_at' => 'datetime', 'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function employeeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_user_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }

    public function developmentPlan(): BelongsTo
    {
        return $this->belongsTo(DevelopmentPlan::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_user_id');
    }

    public function hrReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hr_user_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TrainingAttachment::class);
    }
}
