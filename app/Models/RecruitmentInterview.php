<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecruitmentInterview extends Model
{
    protected $fillable = [
        'recruitment_candidate_id',
        'round',
        'interview_type',
        'scheduled_at',
        'duration_minutes',
        'location_or_link',
        'panel_user_ids',
        'status',
        'overall_score',
        'overall_recommendation',
        'notes',
        'created_by',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'round' => 'integer',
            'scheduled_at' => 'datetime',
            'duration_minutes' => 'integer',
            'panel_user_ids' => 'array',
            'overall_score' => 'decimal:2',
            'completed_at' => 'datetime',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(RecruitmentCandidate::class, 'recruitment_candidate_id');
    }

    public function scorecards(): HasMany
    {
        return $this->hasMany(RecruitmentScorecard::class);
    }
}
