<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeparationTemplate extends Model
{
    public const TYPES = [
        'resignation', 'contract_end', 'retirement', 'termination',
        'redundancy', 'medical', 'death', 'other',
    ];

    protected $fillable = [
        'code', 'name', 'description', 'separation_type',
        'minimum_notice_days', 'employee_can_apply',
        'exit_interview_required', 'final_settlement_required',
        'approver_user_id', 'is_active', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'minimum_notice_days' => 'integer',
            'employee_can_apply' => 'boolean',
            'exit_interview_required' => 'boolean',
            'final_settlement_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ClearanceTemplateItem::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    public function cases(): HasMany
    {
        return $this->hasMany(SeparationCase::class);
    }
}
