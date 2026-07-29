<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaklumatJawatan extends Model
{
    protected $connection = 'ibco';

    protected $table = 'maklumatjawatan';

    public $timestamps = false;

    protected $fillable = [
        'id_pekerja',
        'date_lapordiri',
        'date_tempohcubaan',
        'id_department',
        'jawatan',
        'salary',
        'id_bank',
        'noakaun',
        'noepf',
        'nosocso',
        'jumlahcuti',
        'rcd_enable',
        'crt_by',
        'crt_dt',
        'mdf_by',
        'mdf_dt',
    ];
}
