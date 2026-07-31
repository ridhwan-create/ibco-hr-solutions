<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftSwapRequest extends Model
{
    public const STATUSES = ['pending', 'approved', 'rejected', 'cancelled'];

    protected $fillable = [
        'requester_roster_entry_id',
        'target_roster_entry_id',
        'requester_user_id',
        'target_user_id',
        'department_id',
        'reason',
        'status',
        'reviewed_at',
        'reviewed_by',
        'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'requester_roster_entry_id' => 'integer',
            'target_roster_entry_id' => 'integer',
            'requester_user_id' => 'integer',
            'target_user_id' => 'integer',
            'department_id' => 'integer',
            'reviewed_by' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function requesterEntry(): BelongsTo
    {
        return $this->belongsTo(
            RosterEntry::class,
            'requester_roster_entry_id',
        );
    }

    public function targetEntry(): BelongsTo
    {
        return $this->belongsTo(RosterEntry::class, 'target_roster_entry_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
