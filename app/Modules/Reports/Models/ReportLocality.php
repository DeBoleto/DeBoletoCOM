<?php

namespace App\Modules\Reports\Models;

use Illuminate\Database\Eloquent\Model;

class ReportLocality extends Model
{
    protected $table = 'report_locality';

    public $timestamps = false;

    protected $primaryKey = 'id';

    protected $fillable = ['event_id', 'xml'];
}
