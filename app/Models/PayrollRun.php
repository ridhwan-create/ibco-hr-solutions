<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    public const STATUSES = [
        'draft',
        'hr_reviewed',
        'approved',
        'finalized',
    ];

    protected $fillable = [
        'period_start',
        'status',
        'currency',
        'employee_count',
        'total_basic_salary',
        'total_earnings',
        'total_deductions',
        'total_net_pay',
        'total_employee_statutory',
        'total_employer_statutory',
        'total_pcb',
        'generated_at',
        'generated_by',
        'reviewed_at',
        'reviewed_by',
        'review_notes',
        'approved_at',
        'approved_by',
        'approval_notes',
        'finalized_at',
        'finalized_by',
        'returned_to_draft_at',
        'returned_to_draft_by',
        'return_reason',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'employee_count' => 'integer',
            'total_basic_salary' => 'decimal:2',
            'total_earnings' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'total_net_pay' => 'decimal:2',
            'total_employee_statutory' => 'decimal:2',
            'total_employer_statutory' => 'decimal:2',
            'total_pcb' => 'decimal:2',
            'generated_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'finalized_at' => 'datetime',
            'returned_to_draft_at' => 'datetime',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(PayrollEntry::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function returnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_to_draft_by');
    }
}
