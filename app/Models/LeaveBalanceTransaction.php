<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalanceTransaction extends Model
{
    protected $fillable = [
        'leave_entitlement_id',
        'leave_request_id',
        'transaction_type',
        'days',
        'notes',
        'performed_by',
    ];

    protected function casts(): array
    {
        return [
            'days' => 'decimal:1',
        ];
    }

    public function entitlement(): BelongsTo
    {
        return $this->belongsTo(LeaveEntitlement::class, 'leave_entitlement_id');
    }
}
