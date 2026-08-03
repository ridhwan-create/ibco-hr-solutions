<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplineResponse extends Model
{
    protected $fillable = [
        'discipline_case_id', 'user_id', 'response_type', 'statement',
        'is_confidential', 'submitted_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_confidential' => 'boolean',
            'submitted_at' => 'datetime',
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
