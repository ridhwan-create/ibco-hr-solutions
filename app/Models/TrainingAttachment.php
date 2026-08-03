<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingAttachment extends Model
{
    protected $fillable = [
        'training_request_id', 'attachment_type', 'disk', 'path',
        'original_name', 'mime_type', 'size', 'valid_until', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return ['valid_until' => 'date'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(TrainingRequest::class, 'training_request_id');
    }
}
