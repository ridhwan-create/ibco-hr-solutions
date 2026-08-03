<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Competency extends Model
{
    protected $fillable = [
        'code', 'name', 'category', 'description', 'maximum_level',
        'level_descriptions', 'is_active', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['maximum_level' => 'integer', 'level_descriptions' => 'array', 'is_active' => 'boolean'];
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(CompetencyRequirement::class);
    }
}
