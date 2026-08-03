<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplineAttachment extends Model
{
    protected $fillable = [
        'discipline_case_id', 'uploaded_by', 'attachment_context',
        'original_name', 'disk', 'path', 'mime_type', 'size',
        'visible_to_complainant', 'visible_to_subject',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'visible_to_complainant' => 'boolean',
            'visible_to_subject' => 'boolean',
        ];
    }

    public function disciplineCase(): BelongsTo
    {
        return $this->belongsTo(DisciplineCase::class);
    }
}
