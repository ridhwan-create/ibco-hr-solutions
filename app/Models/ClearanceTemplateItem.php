<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClearanceTemplateItem extends Model
{
    public const OWNER_TYPES = [
        'employee', 'supervisor', 'hr', 'finance', 'ict',
        'administration', 'payroll', 'custom',
    ];

    protected $fillable = [
        'separation_template_id', 'title', 'description', 'owner_type',
        'assignee_user_id', 'due_offset_days', 'is_mandatory',
        'employee_action_required', 'evidence_required', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'due_offset_days' => 'integer',
            'is_mandatory' => 'boolean',
            'employee_action_required' => 'boolean',
            'evidence_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(SeparationTemplate::class, 'separation_template_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }
}
