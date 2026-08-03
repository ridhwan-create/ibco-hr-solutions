<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingCourse extends Model
{
    protected $fillable = [
        'training_provider_id', 'code', 'title', 'category', 'delivery_method',
        'description', 'learning_objectives', 'duration_hours', 'cpd_points',
        'default_cost', 'currency', 'certificate_validity_months',
        'is_mandatory', 'is_active', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'duration_hours' => 'decimal:2',
            'cpd_points' => 'decimal:2',
            'default_cost' => 'decimal:2',
            'is_mandatory' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(TrainingProvider::class, 'training_provider_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class);
    }
}
