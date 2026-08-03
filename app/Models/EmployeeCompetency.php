<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeCompetency extends Model
{
    protected $fillable = [
        'employee_user_id', 'employee_id', 'competency_id', 'current_level',
        'assessment_source', 'evidence_notes', 'assessed_by', 'assessed_at',
    ];

    protected function casts(): array
    {
        return ['employee_id' => 'integer', 'current_level' => 'integer', 'assessed_at' => 'datetime'];
    }

    public function employeeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_user_id');
    }

    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class);
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }
}
