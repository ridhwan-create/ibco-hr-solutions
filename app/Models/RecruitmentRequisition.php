<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecruitmentRequisition extends Model
{
    public const STATUSES = [
        'draft',
        'pending_approval',
        'approved',
        'published',
        'on_hold',
        'closed',
        'cancelled',
    ];

    protected $fillable = [
        'code',
        'title',
        'department_id',
        'position_name',
        'employment_type',
        'vacancies',
        'hiring_manager_user_id',
        'location',
        'description',
        'requirements',
        'min_salary',
        'max_salary',
        'target_hire_date',
        'status',
        'approval_notes',
        'created_by',
        'approved_by',
        'submitted_at',
        'approved_at',
        'published_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'department_id' => 'integer',
            'vacancies' => 'integer',
            'min_salary' => 'decimal:2',
            'max_salary' => 'decimal:2',
            'target_hire_date' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function hiringManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hiring_manager_user_id');
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(RecruitmentCandidate::class);
    }
}
