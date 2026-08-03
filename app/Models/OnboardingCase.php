<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnboardingCase extends Model
{
    protected $fillable = [
        'recruitment_candidate_id',
        'recruitment_offer_id',
        'onboarding_template_id',
        'legacy_employee_id',
        'employee_record_id',
        'employee_user_id',
        'manager_user_id',
        'buddy_user_id',
        'start_date',
        'status',
        'notes',
        'created_by',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'legacy_employee_id' => 'integer',
            'start_date' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
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

    public function template(): BelongsTo
    {
        return $this->belongsTo(OnboardingTemplate::class, 'onboarding_template_id');
    }

    public function employeeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_user_id');
    }

    public function employeeRecord(): BelongsTo
    {
        return $this->belongsTo(EmployeeRecord::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function buddy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buddy_user_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(OnboardingTask::class)->orderBy('sort_order');
    }
}
