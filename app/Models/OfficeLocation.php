<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfficeLocation extends Model
{
    protected $fillable = [
        'name',
        'address',
        'latitude',
        'longitude',
        'radius_meters',
        'accuracy_limit_meters',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'radius_meters' => 'integer',
            'accuracy_limit_meters' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function employeeLinks(): HasMany
    {
        return $this->hasMany(EmployeeUserLink::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(GeoAttendanceRecord::class);
    }
}
