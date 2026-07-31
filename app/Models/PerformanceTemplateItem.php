<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceTemplateItem extends Model
{
    protected $fillable = [
        'performance_template_id',
        'title',
        'description',
        'measure_type',
        'target_value',
        'unit',
        'weight',
        'scoring_guide',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'target_value' => 'decimal:2',
            'weight' => 'decimal:2',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(PerformanceTemplate::class, 'performance_template_id');
    }
}
