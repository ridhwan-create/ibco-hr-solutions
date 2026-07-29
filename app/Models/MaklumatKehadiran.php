<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaklumatKehadiran extends Model
{
    protected $connection = 'ibco';

    protected $table = 'maklumatkehadiran';

    public $timestamps = false;
}
