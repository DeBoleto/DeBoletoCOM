<?php

namespace App\Modules\Events\Models;

use Illuminate\Database\Eloquent\Model;
use DB;
use Auth;

class EventosComisionesTipos extends Model
{
    protected $table = "eventos_comisiones_tipos";
    public $timestamps = false;
    protected $primaryKey = 'pk_evento_comision_tipo';

    protected $fillable = ['evento_comision_tipo'];
}
