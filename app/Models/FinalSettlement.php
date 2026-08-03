<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinalSettlement extends Model
{
    protected $fillable = [
        'separation_case_id', 'salary_due', 'leave_encashment', 'gratuity',
        'claims_due', 'other_payments', 'notice_deduction', 'asset_deduction',
        'loan_deduction', 'other_deductions', 'net_amount', 'notes', 'status',
        'prepared_by', 'prepared_at', 'verified_by', 'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'salary_due' => 'decimal:2',
            'leave_encashment' => 'decimal:2',
            'gratuity' => 'decimal:2',
            'claims_due' => 'decimal:2',
            'other_payments' => 'decimal:2',
            'notice_deduction' => 'decimal:2',
            'asset_deduction' => 'decimal:2',
            'loan_deduction' => 'decimal:2',
            'other_deductions' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'prepared_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function separationCase(): BelongsTo
    {
        return $this->belongsTo(SeparationCase::class);
    }
}
