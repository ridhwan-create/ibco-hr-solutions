<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingNotification extends Model
{
    protected $fillable = ['user_id', 'training_request_id', 'type', 'title', 'message', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(TrainingRequest::class, 'training_request_id');
    }
}
