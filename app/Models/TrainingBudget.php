<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingBudget extends Model
{
    protected $fillable = [
        'year', 'department_id', 'budget_code', 'allocated_amount', 'notes',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['year' => 'integer', 'department_id' => 'integer', 'allocated_amount' => 'decimal:2'];
    }
}
