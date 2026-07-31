<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerformanceCycle extends Model
{
    public const TYPES = ['annual', 'half_year', 'probation'];

    public const STATUSES = ['draft', 'open', 'in_review', 'finalized'];

    protected $fillable = [
        'code',
        'name',
        'cycle_type',
        'period_start',
        'period_end',
        'self_assessment_due_at',
        'supervisor_due_at',
        'moderation_due_at',
        'status',
        'rating_scale',
        'created_by',
        'updated_by',
        'opened_at',
        'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'self_assessment_due_at' => 'date',
            'supervisor_due_at' => 'date',
            'moderation_due_at' => 'date',
            'rating_scale' => 'array',
            'opened_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(PerformanceReview::class);
    }
}
