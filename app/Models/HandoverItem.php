<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HandoverItem extends Model
{
    public const STATUSES = ['pending', 'submitted', 'accepted', 'rejected', 'waived'];

    protected $fillable = [
        'separation_case_id', 'title', 'description', 'recipient_user_id',
        'due_date', 'status', 'submission_notes', 'submitted_by',
        'submitted_at', 'review_notes', 'reviewed_by', 'reviewed_at',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function separationCase(): BelongsTo
    {
        return $this->belongsTo(SeparationCase::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}
