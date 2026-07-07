<?php

namespace App\Modules\Events\Models;

use Illuminate\Database\Eloquent\Model;
use DB;
use Auth;

class EventosStatus extends Model
{
    protected $table = "eventos_status";
    public $timestamps = false;
    protected $primaryKey = 'pk_evento_status';

    protected $fillable = ['evento_status'];

    /* RELATIONSHIPS - INICIO */
    public function eventos() {
        return $this->hasMany('App\Modules\Events\Models\Eventos', 'pk_evento_status', 'pk_evento_status');
    }
    /* RELATIONSHIPS - FIN */
}
