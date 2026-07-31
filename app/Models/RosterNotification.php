<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RosterNotification extends Model
{
    protected $fillable = [
        'user_id',
        'roster_period_id',
        'shift_swap_request_id',
        'title',
        'message',
        'read_at',
    ];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(RosterPeriod::class, 'roster_period_id');
    }

    public function shiftSwapRequest(): BelongsTo
    {
        return $this->belongsTo(ShiftSwapRequest::class);
    }
}
