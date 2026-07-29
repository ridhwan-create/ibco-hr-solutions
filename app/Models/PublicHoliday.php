<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicHoliday extends Model
{
    protected $fillable = [
        'name',
        'holiday_date',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'holiday_date' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
