<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DevelopmentPlan extends Model
{
    protected $fillable = [
        'employee_user_id', 'employee_id', 'competency_id',
        'performance_review_id', 'performance_improvement_plan_id', 'source',
        'title', 'action_plan', 'target_level', 'due_date', 'status',
        'completion_notes', 'created_by', 'updated_by', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'employee_id' => 'integer', 'target_level' => 'integer',
            'due_date' => 'date', 'completed_at' => 'datetime',
        ];
    }

    public function employeeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_user_id');
    }

    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class);
    }

    public function trainingRequests(): HasMany
    {
        return $this->hasMany(TrainingRequest::class);
    }
}
