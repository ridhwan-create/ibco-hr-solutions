<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetencyRequirement extends Model
{
    protected $fillable = [
        'competency_id', 'department_id', 'position_name', 'required_level',
        'is_mandatory', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return ['department_id' => 'integer', 'required_level' => 'integer', 'is_mandatory' => 'boolean'];
    }

    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class);
    }
}
