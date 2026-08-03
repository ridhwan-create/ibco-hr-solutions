<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeUserLink extends Model
{
    protected $fillable = [
        'user_id',
        'employee_id',
        'employee_source',
        'employee_record_id',
        'office_location_id',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'employee_record_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function officeLocation(): BelongsTo
    {
        return $this->belongsTo(OfficeLocation::class);
    }

    public function employeeRecord(): BelongsTo
    {
        return $this->belongsTo(EmployeeRecord::class);
    }
}
