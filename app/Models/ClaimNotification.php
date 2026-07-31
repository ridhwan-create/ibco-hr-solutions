<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimNotification extends Model
{
    protected $fillable = [
        'user_id',
        'claim_request_id',
        'title',
        'message',
        'read_at',
    ];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function claimRequest(): BelongsTo
    {
        return $this->belongsTo(ClaimRequest::class);
    }
}
