<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingTask extends Model
{
    protected $fillable = [
        'onboarding_case_id',
        'title',
        'description',
        'category',
        'assignee_role',
        'assignee_user_id',
        'due_date',
        'is_required',
        'status',
        'completion_notes',
        'completed_by',
        'completed_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'is_required' => 'boolean',
            'completed_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function onboardingCase(): BelongsTo
    {
        return $this->belongsTo(OnboardingCase::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }
}
