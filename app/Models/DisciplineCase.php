<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DisciplineCase extends Model
{
    public const STATUSES = [
        'submitted', 'triage', 'investigation', 'show_cause_pending',
        'show_cause', 'decision', 'appeal', 'closed', 'dismissed', 'withdrawn',
    ];

    public const FINDING_OUTCOMES = [
        'substantiated', 'partially_substantiated', 'unsubstantiated', 'inconclusive',
    ];

    public const DECISION_OUTCOMES = [
        'no_action', 'counselling', 'verbal_warning', 'written_warning',
        'final_warning', 'suspension', 'demotion', 'termination', 'other',
    ];

    protected $fillable = [
        'case_number', 'complaint_category_id', 'complainant_user_id',
        'complainant_employee_id', 'complainant_employee_number',
        'complainant_name', 'complainant_email', 'complainant_department_id',
        'complainant_department_name', 'identity_protected', 'subject_user_id',
        'subject_employee_id', 'subject_employee_number', 'subject_name',
        'subject_email', 'subject_department_id', 'subject_department_name',
        'subject_position_name', 'title', 'incident_at', 'incident_location',
        'description', 'requested_resolution', 'severity', 'confidentiality',
        'status', 'triage_notes', 'triaged_by', 'triaged_at',
        'investigator_user_id', 'investigation_started_at',
        'target_completion_date', 'allegation_summary', 'finding_outcome',
        'finding_summary', 'recommended_action', 'finding_submitted_by',
        'finding_submitted_at', 'show_cause_due_at', 'decision_outcome',
        'decision_notes', 'decided_by', 'decided_at', 'effective_date',
        'appeal_deadline', 'hr_document_id', 'closed_by', 'closed_at',
        'closure_reason', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'complainant_employee_id' => 'integer',
            'complainant_department_id' => 'integer',
            'identity_protected' => 'boolean',
            'subject_employee_id' => 'integer',
            'subject_department_id' => 'integer',
            'incident_at' => 'datetime',
            'triaged_at' => 'datetime',
            'investigation_started_at' => 'datetime',
            'target_completion_date' => 'date',
            'finding_submitted_at' => 'datetime',
            'show_cause_due_at' => 'datetime',
            'decided_at' => 'datetime',
            'effective_date' => 'date',
            'appeal_deadline' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ComplaintCategory::class, 'complaint_category_id');
    }

    public function complainant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'complainant_user_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    public function investigator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'investigator_user_id');
    }

    public function hrDocument(): BelongsTo
    {
        return $this->belongsTo(HrDocument::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(DisciplineCaseMember::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(DisciplineCaseEvent::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(DisciplineAttachment::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(DisciplineResponse::class);
    }

    public function appeals(): HasMany
    {
        return $this->hasMany(DisciplineAppeal::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['closed', 'dismissed', 'withdrawn']);
    }
}
