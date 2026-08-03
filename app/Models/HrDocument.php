<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrDocument extends Model
{
    public const STATUSES = [
        'draft', 'pending_approval', 'approved', 'rejected',
        'issued', 'acknowledged', 'voided',
    ];

    public const SOURCES = [
        'manual', 'recruitment', 'onboarding', 'performance',
        'payroll', 'discipline', 'separation',
    ];

    protected $fillable = [
        'reference_number', 'document_template_id', 'template_code',
        'template_name', 'category', 'employee_user_id', 'employee_id',
        'employee_number', 'employee_name', 'employee_email', 'department_id',
        'department_name', 'position_name', 'source_type', 'source_id',
        'subject', 'body', 'template_snapshot', 'custom_variables',
        'signatory_name', 'signatory_position', 'internal_notes', 'status',
        'approval_required', 'approver_user_id', 'submitted_by', 'submitted_at',
        'approved_by', 'approved_at', 'approval_notes', 'rejected_by',
        'rejected_at', 'rejection_reason', 'issued_by', 'issued_at',
        'effective_date', 'expiry_date', 'acknowledgement_required',
        'acknowledged_by', 'acknowledged_at', 'acknowledgement_ip',
        'voided_by', 'voided_at', 'void_reason', 'supersedes_document_id',
        'confidentiality', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'department_id' => 'integer',
            'source_id' => 'integer',
            'template_snapshot' => 'array',
            'custom_variables' => 'array',
            'approval_required' => 'boolean',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'issued_at' => 'datetime',
            'effective_date' => 'date',
            'expiry_date' => 'date',
            'acknowledgement_required' => 'boolean',
            'acknowledged_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'document_template_id');
    }

    public function employeeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(HrDocumentAttachment::class);
    }

    public function supersededDocument(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_document_id');
    }

    public function scopeIssuedTo(Builder $query, int $userId): Builder
    {
        return $query
            ->where('employee_user_id', $userId)
            ->whereIn('status', ['issued', 'acknowledged']);
    }

    public function isExpired(): bool
    {
        return in_array($this->status, ['issued', 'acknowledged'], true)
            && $this->expiry_date?->isBefore(today());
    }

    public function displayStatus(): string
    {
        return $this->isExpired() ? 'expired' : $this->status;
    }
}
