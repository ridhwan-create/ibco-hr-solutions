<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingTemplateTask extends Model
{
    protected $fillable = [
        'onboarding_template_id',
        'title',
        'description',
        'category',
        'assignee_role',
        'due_offset_days',
        'is_required',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'due_offset_days' => 'integer',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(OnboardingTemplate::class, 'onboarding_template_id');
    }
}
