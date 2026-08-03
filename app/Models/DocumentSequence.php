<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentSequence extends Model
{
    protected $fillable = [
        'sequence_key', 'name', 'prefix', 'format', 'next_number', 'last_year',
        'reset_annually', 'is_active', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'next_number' => 'integer',
            'last_year' => 'integer',
            'reset_annually' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
