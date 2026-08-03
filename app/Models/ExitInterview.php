<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExitInterview extends Model
{
    protected $fillable = [
        'separation_case_id', 'interviewer_user_id', 'scheduled_at',
        'employee_submitted_at', 'completed_at', 'primary_reason',
        'employment_experience_rating', 'manager_support_rating',
        'would_recommend', 'positive_feedback', 'improvement_feedback',
        'additional_feedback', 'hr_private_notes', 'completed_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'employee_submitted_at' => 'datetime',
            'completed_at' => 'datetime',
            'employment_experience_rating' => 'integer',
            'manager_support_rating' => 'integer',
            'would_recommend' => 'boolean',
        ];
    }

    public function separationCase(): BelongsTo
    {
        return $this->belongsTo(SeparationCase::class);
    }

    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'interviewer_user_id');
    }
}
