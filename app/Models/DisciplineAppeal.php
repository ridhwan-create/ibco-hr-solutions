<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplineAppeal extends Model
{
    protected $fillable = [
        'discipline_case_id', 'appellant_user_id', 'grounds',
        'desired_outcome', 'status', 'reviewed_by', 'reviewed_at',
        'decision_notes', 'revised_outcome',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function disciplineCase(): BelongsTo
    {
        return $this->belongsTo(DisciplineCase::class);
    }

    public function appellant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'appellant_user_id');
    }
}
