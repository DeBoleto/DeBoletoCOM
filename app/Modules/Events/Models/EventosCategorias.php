<?php

namespace App\Modules\Events\Models;

use Illuminate\Database\Eloquent\Model;
use DB;
use Auth;

class EventosCategorias extends Model
{
    protected $table = "eventos_categorias";
    public $timestamps = false;
    protected $primaryKey = 'pk_evento_categoria';

    protected $fillable = ['evento_categoria', 'creacion_pk_usuario', 'creacion_fecha', 'modificacion_pk_usuario', 'modificacion_fecha', 'eliminado'];

    /* RELATIONSHIP - INICIO */
    public function eventos() {
        return $this->belongsToMany('App\Modules\Events\Models\Eventos', 'eventos_map_eventos_categorias', 'pk_evento_categoria', 'pk_evento');
    }

    public function creacionUsuario() {
        return $this->belongsTo('App\Modules\Users\Models\Usuarios', 'pk_usuario', 'creacion_pk_usuario');
    }

    public function modificacionUsuario() {
        return $this->belongsTo('App\Modules\Users\Models\Usuarios', 'pk_usuario', 'modificacion_pk_usuario');
    }
    /* RELATIONSHIP - FIN */


    static public function view() {
        $retorno = DB::table('view_eventos_categorias');
        return $retorno;
    }

    static public function viewFind($pk_evento_categoria) {
        $retorno = DB::table('view_eventos_categorias');
        $retorno->where('pk_evento_categoria', '=', $pk_evento_categoria);
        return $retorno->get()->first();
    }

    public function save(array $options = array()) {

        $this['modificacion_pk_usuario'] = Auth::user()->pk_usuario;
        $this['modificacion_fecha'] = date('Y-m-d H:i:s');

        return parent::save($options);
    }

    public function create(array $options = array()) {
        if( $this['pk_usuario_documento'] === null) {
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

    public function delete() {
        if( $this['eliminado'] == 0) {
            $this['modificacion_pk_usuario'] = Auth::user()->pk_usuario;
            $this['modificacion_fecha'] = date('Y-m-d H:i:s');
            $this['eliminado'] = 1;
            return save();
        } else {
            return false;
        }
    }
}
