<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SeparationCase extends Model
{
    public const STATUSES = [
        'draft', 'pending_approval', 'approved', 'clearance',
        'final_review', 'completed', 'rejected', 'cancelled',
    ];

    protected $fillable = [
        'case_number', 'separation_template_id', 'employee_user_id',
        'employee_id', 'employee_number', 'employee_name', 'employee_email',
        'department_id', 'department_name', 'position_name',
        'separation_type', 'initiated_by_employee', 'reason_category',
        'reason_details', 'notice_submitted_date', 'proposed_last_day',
        'approved_last_day', 'notice_days_required', 'notice_days_served',
        'notice_shortfall_days', 'notice_waived', 'waiver_notes', 'status',
        'approval_stage', 'supervisor_user_id', 'supervisor_decision',
        'supervisor_notes', 'supervisor_decided_by', 'supervisor_decided_at',
        'hr_approver_user_id', 'hr_decision', 'hr_notes', 'hr_decided_by',
        'hr_decided_at', 'clearance_started_at', 'clearance_due_date',
        'acceptance_document_id', 'clearance_document_id',
        'eligible_for_rehire', 'closure_notes', 'completed_by',
        'completed_at', 'cancelled_by', 'cancelled_at',
        'cancellation_reason', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'department_id' => 'integer',
            'initiated_by_employee' => 'boolean',
            'notice_submitted_date' => 'date',
            'proposed_last_day' => 'date',
            'approved_last_day' => 'date',
            'notice_days_required' => 'integer',
            'notice_days_served' => 'integer',
            'notice_shortfall_days' => 'integer',
            'notice_waived' => 'boolean',
            'supervisor_decided_at' => 'datetime',
            'hr_decided_at' => 'datetime',
            'clearance_started_at' => 'datetime',
            'clearance_due_date' => 'date',
            'eligible_for_rehire' => 'boolean',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(SeparationTemplate::class, 'separation_template_id');
    }

    public function employeeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_user_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_user_id');
    }

    public function hrApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hr_approver_user_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ClearanceTask::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SeparationAttachment::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(SeparationAsset::class);
    }

    public function handovers(): HasMany
    {
        return $this->hasMany(HandoverItem::class);
    }

    public function interview(): HasOne
    {
        return $this->hasOne(ExitInterview::class);
    }

    public function settlement(): HasOne
    {
        return $this->hasOne(FinalSettlement::class);
    }

    public function acceptanceDocument(): BelongsTo
    {
        return $this->belongsTo(HrDocument::class, 'acceptance_document_id');
    }

    public function clearanceDocument(): BelongsTo
    {
        return $this->belongsTo(HrDocument::class, 'clearance_document_id');
    }

    public function scopeForEmployee(Builder $query, int $userId): Builder
    {
        return $query->where('employee_user_id', $userId);
    }

    public function mandatoryClearanceComplete(): bool
    {
        return ! $this->tasks()
            ->where('is_mandatory', true)
            ->whereNotIn('status', ['completed', 'waived'])
            ->exists();
    }
}
