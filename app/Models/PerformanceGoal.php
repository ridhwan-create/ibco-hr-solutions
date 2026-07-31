<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerformanceGoal extends Model
{
    protected $fillable = [
        'performance_review_id',
        'performance_template_item_id',
        'title',
        'description',
        'measure_type',
        'target_value',
        'unit',
        'weight',
        'scoring_guide',
        'actual_achievement',
        'self_score',
        'self_comments',
        'supervisor_score',
        'supervisor_comments',
        'moderated_score',
        'moderation_comments',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'target_value' => 'decimal:2',
            'weight' => 'decimal:2',
            'self_score' => 'decimal:2',
            'supervisor_score' => 'decimal:2',
            'moderated_score' => 'decimal:2',
        ];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(PerformanceReview::class, 'performance_review_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(PerformanceEvidence::class);
    }
}
