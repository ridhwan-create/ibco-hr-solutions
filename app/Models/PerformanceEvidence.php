<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceEvidence extends Model
{
    protected $table = 'performance_evidence';

    protected $fillable = [
        'performance_review_id',
        'performance_goal_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'description',
        'uploaded_by',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(PerformanceReview::class, 'performance_review_id');
    }

    public function goal(): BelongsTo
    {
        return $this->belongsTo(PerformanceGoal::class, 'performance_goal_id');
    }
}
