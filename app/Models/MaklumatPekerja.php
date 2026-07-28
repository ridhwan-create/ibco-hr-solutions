<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaklumatPekerja extends Model
{
    use HasFactory;

    // Nyatakan nama jadual kerana ia bukan format plural standard bahasa Inggeris
    protected $table = 'maklumatpekerja';

    // Disable default timestamps (created_at, updated_at) sebab jadual guna crt_dt, mdf_dt
    public $timestamps = false;

    protected $fillable = [
        'nric',
        'employeeID',
        'nama',
        'alamat',
        'jantina',
        'tarikhlahir',
        'agama',
        'bangsa',
        'kewarganegaraan',
        'statusperkahwinan',
        'notel',
        'email',
        'status',
        'rcd_enable',
        'crt_by',
        'crt_dt',
        'mdf_by',
        'mdf_dt',
    ];
}
