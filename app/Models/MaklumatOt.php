<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaklumatOt extends Model
{
    protected $connection = 'ibco';

    protected $table = 'maklumatot';

    public $timestamps = false;
}
