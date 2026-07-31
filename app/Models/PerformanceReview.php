<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PerformanceReview extends Model
{
    public const STATUSES = [
        'goal_setting',
        'self_assessment',
        'supervisor_assessment',
        'hr_moderation',
        'finalized',
    ];

    protected $fillable = [
        'performance_cycle_id',
        'performance_template_id',
        'employee_id',
        'employee_user_id',
        'supervisor_user_id',
        'department_id',
        'position_name',
        'status',
        'total_weight',
        'self_score',
        'supervisor_score',
        'moderated_score',
        'final_rating',
        'employee_summary',
        'supervisor_summary',
        'strengths',
        'improvement_areas',
        'development_plan',
        'hr_comments',
        'self_submitted_at',
        'supervisor_submitted_at',
        'moderated_at',
        'moderated_by',
        'finalized_at',
        'finalized_by',
    ];

    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'department_id' => 'integer',
            'total_weight' => 'decimal:2',
            'self_score' => 'decimal:2',
            'supervisor_score' => 'decimal:2',
            'moderated_score' => 'decimal:2',
            'self_submitted_at' => 'datetime',
            'supervisor_submitted_at' => 'datetime',
            'moderated_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(PerformanceCycle::class, 'performance_cycle_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(PerformanceTemplate::class, 'performance_template_id');
    }

    public function employeeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_user_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_user_id');
    }

    public function goals(): HasMany
    {
        return $this->hasMany(PerformanceGoal::class)->orderBy('sort_order');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(PerformanceEvidence::class);
    }

    public function improvementPlan(): HasOne
    {
        return $this->hasOne(PerformanceImprovementPlan::class);
    }
}
