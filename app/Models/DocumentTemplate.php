<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentTemplate extends Model
{
    public const CATEGORIES = [
        'contract', 'confirmation', 'salary_revision', 'promotion', 'transfer',
        'warning', 'show_cause', 'memo', 'termination', 'resignation',
        'clearance', 'custom',
    ];

    public const CONFIDENTIALITY_LEVELS = ['internal', 'confidential', 'restricted'];

    protected $fillable = [
        'code', 'name', 'category', 'subject_template', 'body_template',
        'available_variables', 'sequence_key', 'requires_approval',
        'approver_user_id', 'acknowledgement_required',
        'default_validity_months', 'confidentiality', 'is_active',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'available_variables' => 'array',
            'requires_approval' => 'boolean',
            'acknowledgement_required' => 'boolean',
            'default_validity_months' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(HrDocument::class);
    }
}
