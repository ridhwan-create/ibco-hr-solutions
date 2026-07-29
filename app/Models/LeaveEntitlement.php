<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveEntitlement extends Model
{
    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'year',
        'entitled_days',
        'carry_forward_days',
        'adjustment_days',
        'notes',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'year' => 'integer',
            'entitled_days' => 'decimal:1',
            'carry_forward_days' => 'decimal:1',
            'adjustment_days' => 'decimal:1',
        ];
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LeaveBalanceTransaction::class);
    }
}
