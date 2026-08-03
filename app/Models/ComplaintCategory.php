<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComplaintCategory extends Model
{
    public const SEVERITIES = ['low', 'medium', 'high', 'critical'];

    protected $fillable = [
        'code', 'name', 'description', 'default_severity', 'sla_days',
        'appeal_days', 'requires_show_cause', 'allow_protected_identity',
        'is_active', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'sla_days' => 'integer',
            'appeal_days' => 'integer',
            'requires_show_cause' => 'boolean',
            'allow_protected_identity' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function cases(): HasMany
    {
        return $this->hasMany(DisciplineCase::class);
    }
}
