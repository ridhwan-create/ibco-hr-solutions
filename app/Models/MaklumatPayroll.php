<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaklumatPayroll extends Model
{
    protected $connection = 'ibco';

    protected $table = 'maklumatpayroll';

    public $timestamps = false;
}
