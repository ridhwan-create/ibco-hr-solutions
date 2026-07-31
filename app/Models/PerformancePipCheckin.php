<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformancePipCheckin extends Model
{
    protected $fillable = [
        'performance_improvement_plan_id',
        'checkin_date',
        'progress_status',
        'progress_notes',
        'next_actions',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return ['checkin_date' => 'date'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(
            PerformanceImprovementPlan::class,
            'performance_improvement_plan_id',
        );
    }
}
