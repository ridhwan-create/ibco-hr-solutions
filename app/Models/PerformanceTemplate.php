<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerformanceTemplate extends Model
{
    protected $fillable = [
        'code',
        'name',
        'department_id',
        'position_name',
        'description',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'department_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PerformanceTemplateItem::class)
            ->orderBy('sort_order');
    }
}
