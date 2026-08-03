<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClearanceTask extends Model
{
    public const STATUSES = [
        'pending', 'in_progress', 'submitted', 'completed', 'rejected', 'waived',
    ];

    protected $fillable = [
        'separation_case_id', 'clearance_template_item_id', 'title',
        'description', 'owner_type', 'assigned_user_id', 'is_mandatory',
        'employee_action_required', 'evidence_required', 'due_date', 'status',
        'submission_notes', 'submitted_by', 'submitted_at', 'review_notes',
        'completed_by', 'completed_at', 'waived_by', 'waived_at',
        'waiver_reason', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_mandatory' => 'boolean',
            'employee_action_required' => 'boolean',
            'evidence_required' => 'boolean',
            'due_date' => 'date',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
            'waived_at' => 'datetime',
        ];
    }

    public function separationCase(): BelongsTo
    {
        return $this->belongsTo(SeparationCase::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SeparationAttachment::class);
    }
}
