<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RosterPeriod extends Model
{
    public const STATUSES = ['draft', 'published', 'locked'];

    protected $fillable = [
        'period_start',
        'period_end',
        'status',
        'notes',
        'published_at',
        'published_by',
        'locked_at',
        'locked_by',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'immutable_date',
            'period_end' => 'immutable_date',
            'published_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(RosterEntry::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function locker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }
}
