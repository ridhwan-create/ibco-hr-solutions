<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RecruitmentCandidate extends Model
{
    public const STAGES = [
        'applied',
        'screening',
        'shortlisted',
        'interview',
        'offer',
        'hired',
        'rejected',
        'withdrawn',
    ];

    protected $fillable = [
        'recruitment_requisition_id',
        'candidate_number',
        'name',
        'email',
        'phone',
        'nric',
        'current_company',
        'current_position',
        'expected_salary',
        'notice_period_days',
        'source',
        'stage',
        'rating',
        'owner_user_id',
        'screening_notes',
        'rejection_reason',
        'withdrawal_reason',
        'applied_at',
        'hired_at',
    ];

    protected function casts(): array
    {
        return [
            'expected_salary' => 'decimal:2',
            'notice_period_days' => 'integer',
            'rating' => 'decimal:2',
            'applied_at' => 'datetime',
            'hired_at' => 'datetime',
        ];
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(RecruitmentRequisition::class, 'recruitment_requisition_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(RecruitmentDocument::class);
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(RecruitmentInterview::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(RecruitmentOffer::class);
    }

    public function onboardingCase(): HasOne
    {
        return $this->hasOne(OnboardingCase::class);
    }

    public function employeeRecord(): HasOne
    {
        return $this->hasOne(EmployeeRecord::class);
    }
}
