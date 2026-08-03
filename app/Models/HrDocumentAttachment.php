<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrDocumentAttachment extends Model
{
    protected $fillable = [
        'hr_document_id', 'attachment_type', 'disk', 'path', 'original_name',
        'mime_type', 'size', 'visible_to_employee', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'visible_to_employee' => 'boolean',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(HrDocument::class, 'hr_document_id');
    }
}
