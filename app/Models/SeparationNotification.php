<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeparationNotification extends Model
{
    protected $fillable = [
        'user_id', 'separation_case_id', 'type', 'title', 'message', 'read_at',
    ];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function separationCase(): BelongsTo
    {
        return $this->belongsTo(SeparationCase::class);
    }
}
