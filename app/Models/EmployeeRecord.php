<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EmployeeRecord extends Model
{
    public const DIRECTORY_ID_OFFSET = 7_000_000_000_000_000;

    protected $fillable = [
        'directory_id',
        'recruitment_candidate_id',
        'recruitment_offer_id',
        'user_id',
        'employee_number',
        'name',
        'identity_number',
        'personal_email',
        'official_email',
        'phone',
        'department_id',
        'position_name',
        'employment_type',
        'salary',
        'probation_months',
        'start_date',
        'manager_user_id',
        'office_location_id',
        'status',
        'confirmed_by',
        'confirmed_at',
        'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'directory_id' => 'integer',
            'department_id' => 'integer',
            'salary' => 'decimal:2',
            'probation_months' => 'integer',
            'start_date' => 'date',
            'confirmed_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(RecruitmentCandidate::class, 'recruitment_candidate_id');
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(RecruitmentOffer::class, 'recruitment_offer_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function officeLocation(): BelongsTo
    {
        return $this->belongsTo(OfficeLocation::class);
    }

    public function employeeLink(): HasOne
    {
        return $this->hasOne(EmployeeUserLink::class);
    }

    public function onboardingCase(): HasOne
    {
        return $this->hasOne(OnboardingCase::class);
    }

    public function activate(): void
    {
        if ($this->status !== 'pending_activation' || $this->start_date?->isFuture()) {
            return;
        }

        $this->forceFill([
            'status' => 'active',
            'activated_at' => $this->activated_at ?? now(),
        ])->save();
    }
}
