<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeparationAttachment extends Model
{
    protected $fillable = [
        'separation_case_id', 'clearance_task_id', 'context', 'disk', 'path',
        'original_name', 'mime_type', 'size', 'visible_to_employee', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return ['visible_to_employee' => 'boolean'];
    }

    public function separationCase(): BelongsTo
    {
        return $this->belongsTo(SeparationCase::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(ClearanceTask::class, 'clearance_task_id');
    }
}
