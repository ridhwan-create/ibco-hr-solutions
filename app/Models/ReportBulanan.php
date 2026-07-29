<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportBulanan extends Model
{
    protected $connection = 'ibco';

    protected $table = 'reportbulanan';

    public $timestamps = false;
}
