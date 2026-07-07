<?php

namespace App\Modules\Events\Models;

use Illuminate\Database\Eloquent\Model;
use DB;
use Auth;

class EventosFunciones extends Model
{
    protected $table = "eventos_funciones";
    public $timestamps = false;
    protected $primaryKey = 'pk_evento_funcion';

    protected $fillable = ['pk_evento', 'fecha', 'creacion_pk_usuario', 'creacion_fecha', 'modificacion_pk_usuario', 'modificacion_fecha'];

    /* RELATIONSHIP - INICIO */
    public function evento() {
        return $this->belongTo('App\Modules\Events\Models\Eventos', 'pk_evento', 'pk_evento');
    }

    public function creacionUsuario() {
        return $this->belongsTo('App\Modules\Users\Models\Usuarios', 'pk_usuario', 'creacion_pk_usuario');
    }

    public function modificacionUsuario() {
        return $this->belongsTo('App\Modules\Users\Models\Usuarios', 'pk_usuario', 'modificacion_pk_usuario');
    }
    /* RELATIONSHIP - FIN */


    static public function view() {
        $retorno = DB::table('view_eventos_funciones');
        return $retorno;
    }

    static public function viewFind($pk_evento_funcion) {
        $retorno = DB::table('view_eventos_funciones');
        $retorno->where('pk_evento_funcion', '=', $pk_evento_funcion);
        return $retorno->get()->first();
    }

    public function save(array $options = array()) {

        $this['modificacion_pk_usuario'] = Auth::user()->pk_usuario;
        $this['modificacion_fecha'] = date('Y-m-d H:i:s');

        return parent::save($options);
    }

    public function create(array $options = array()) {
        if( $this['pk_evento_funcion'] === null) {
            $this['creacion_pk_usuario'] = Auth::user()->pk_usuario;
            $this['creacion_fecha'] = date('Y-m-d H:i:s');
            return save($options);
        } else {
            return false;
        }
    }

    public function update(array $attributes = array(), array $options = array()) {
        return save($options);
    }
}
