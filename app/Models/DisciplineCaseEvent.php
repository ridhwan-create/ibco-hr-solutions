<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplineCaseEvent extends Model
{
    protected $fillable = [
        'discipline_case_id', 'event_type', 'title', 'details', 'occurred_at',
        'visible_to_complainant', 'visible_to_subject', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'visible_to_complainant' => 'boolean',
            'visible_to_subject' => 'boolean',
        ];
    }

    public function disciplineCase(): BelongsTo
    {
        return $this->belongsTo(DisciplineCase::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
