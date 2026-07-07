<?php

namespace App\Modules\Events\Models;

use Illuminate\Database\Eloquent\Model;
use DB;
use Auth;

class EventosMapEventosCategorias extends Model
{
    protected $table = "eventos_map_eventos_categorias";
    public $timestamps = false;
    protected $primaryKey = 'pk_evento';

    protected $fillable = ['pk_evento_categoria', 'creacion_pk_usuario', 'creacion_fecha'];

    public function create(array $options = array()) {
        if( $this['pk_evento'] === null) {
            $this['creacion_pk_usuario'] = Auth::user()->pk_usuario;
            $this['creacion_fecha'] = date('Y-m-d H:i:s');
            return save($options);
        } else {
            return false;
        }
    }
}
