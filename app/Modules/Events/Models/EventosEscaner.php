<?php

namespace App\Modules\Events\Models;

use Illuminate\Database\Eloquent\Model;
use DB;
use Auth;

class EventosEscaner extends Model
{
    protected $table = "eventos_escaner";
    public $timestamps = false;
    protected $primaryKey = 'pk_eventos_escaner';

    protected $fillable = ['pk_eventos', 'pk_usuario'];

    /* RELATIONSHIPS - INICIO */
    public function eventos() {
        return $this->hasMany('App\Modules\Events\Models\Eventos', 'pk_eventos', 'pk_eventos');
    }
    /* RELATIONSHIPS - FIN */
}
