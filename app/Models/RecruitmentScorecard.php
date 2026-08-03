<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecruitmentScorecard extends Model
{
    protected $fillable = [
        'recruitment_interview_id',
        'panel_user_id',
        'technical_score',
        'communication_score',
        'culture_score',
        'overall_score',
        'recommendation',
        'strengths',
        'concerns',
        'comments',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'technical_score' => 'decimal:2',
            'communication_score' => 'decimal:2',
            'culture_score' => 'decimal:2',
            'overall_score' => 'decimal:2',
            'submitted_at' => 'datetime',
        ];
    }

    public function interview(): BelongsTo
    {
        return $this->belongsTo(RecruitmentInterview::class, 'recruitment_interview_id');
    }

    public function panelUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'panel_user_id');
    }
}
