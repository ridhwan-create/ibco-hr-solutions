<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerformanceImprovementPlan extends Model
{
    protected $fillable = [
        'performance_review_id',
        'employee_id',
        'supervisor_user_id',
        'status',
        'start_date',
        'end_date',
        'reason',
        'objectives',
        'required_actions',
        'support_required',
        'success_criteria',
        'outcome',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(PerformanceReview::class, 'performance_review_id');
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(
            PerformancePipCheckin::class,
            'performance_improvement_plan_id',
        )->orderByDesc('checkin_date');
    }
}
