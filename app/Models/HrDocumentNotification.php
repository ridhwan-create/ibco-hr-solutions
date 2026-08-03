<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrDocumentNotification extends Model
{
    protected $fillable = [
        'user_id', 'hr_document_id', 'type', 'title', 'message', 'read_at',
    ];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(HrDocument::class, 'hr_document_id');
    }
}
