<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingSession extends Model
{
    protected $fillable = [
        'training_course_id', 'session_code', 'starts_at', 'ends_at',
        'registration_deadline', 'venue', 'facilitator', 'capacity',
        'cost_per_participant', 'budget_code', 'status', 'notes',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'registration_deadline' => 'date',
            'cost_per_participant' => 'decimal:2',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(TrainingCourse::class, 'training_course_id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(TrainingRequest::class);
    }
}
