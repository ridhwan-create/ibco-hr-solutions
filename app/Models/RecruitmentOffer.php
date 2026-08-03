<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecruitmentOffer extends Model
{
    public const STATUSES = [
        'draft',
        'pending_approval',
        'approved',
        'sent',
        'accepted',
        'declined',
        'expired',
        'withdrawn',
    ];

    protected $fillable = [
        'recruitment_candidate_id',
        'offer_number',
        'position_name',
        'department_id',
        'employment_type',
        'salary',
        'start_date',
        'probation_months',
        'expiry_date',
        'terms',
        'status',
        'approval_notes',
        'response_notes',
        'created_by',
        'approved_by',
        'submitted_at',
        'approved_at',
        'sent_at',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'department_id' => 'integer',
            'salary' => 'decimal:2',
            'start_date' => 'date',
            'probation_months' => 'integer',
            'expiry_date' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'sent_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(RecruitmentCandidate::class, 'recruitment_candidate_id');
    }
}
