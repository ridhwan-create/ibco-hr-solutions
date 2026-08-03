<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplineNotification extends Model
{
    protected $fillable = [
        'user_id', 'discipline_case_id', 'type', 'title', 'message', 'read_at',
    ];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function disciplineCase(): BelongsTo
    {
        return $this->belongsTo(DisciplineCase::class);
    }
}
