<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClaimRequest extends Model
{
    public const STATUSES = ['pending', 'approved', 'rejected', 'cancelled'];

    protected $fillable = [
        'user_id',
        'employee_id',
        'department_id',
        'position_id',
        'claim_type_id',
        'expense_date',
        'merchant_name',
        'receipt_number',
        'receipt_fingerprint',
        'requested_amount',
        'approved_amount',
        'description',
        'status',
        'approval_stage',
        'submitted_at',
        'supervisor_reviewed_at',
        'supervisor_reviewed_by',
        'supervisor_review_notes',
        'reviewed_at',
        'reviewed_by',
        'review_notes',
        'scheduled_payroll_period',
        'payroll_run_id',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'department_id' => 'integer',
            'position_id' => 'integer',
            'expense_date' => 'date',
            'requested_amount' => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'supervisor_reviewed_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'scheduled_payroll_period' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function claimType(): BelongsTo
    {
        return $this->belongsTo(ClaimType::class);
    }

    public function supervisorReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_reviewed_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ClaimAttachment::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(ClaimNotification::class);
    }
}
