<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceSupervisorAssignment extends Model
{
    protected $fillable = [
        'department_id',
        'supervisor_user_id',
        'is_active',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'department_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_user_id');
    }
}
