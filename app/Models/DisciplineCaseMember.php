<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplineCaseMember extends Model
{
    protected $fillable = [
        'discipline_case_id', 'user_id', 'role', 'conflict_declared',
        'has_conflict', 'conflict_notes', 'conflict_declared_at',
        'recused_at', 'assigned_by',
    ];

    protected function casts(): array
    {
        return [
            'conflict_declared' => 'boolean',
            'has_conflict' => 'boolean',
            'conflict_declared_at' => 'datetime',
            'recused_at' => 'datetime',
        ];
    }

    public function disciplineCase(): BelongsTo
    {
        return $this->belongsTo(DisciplineCase::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
