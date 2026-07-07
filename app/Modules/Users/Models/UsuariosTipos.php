<?php

namespace App\Modules\Users\Models;

use Illuminate\Database\Eloquent\Model;
use DB;
use Auth;

class UsuariosTipos extends Model
{
    protected $table = "usuarios_tipos";
    public $timestamps = false;
    protected $primaryKey = 'pk_usuario_tipo';

    protected $fillable = ['pk_usuario_tipo', 'usuario_tipo', 'descripcion', 'creacion_pk_usuario', 'creacion_fecha', 'modificacion_pk_usuario', 'modificacion_fecha', 'eliminado'];

    /* RELATIONSHIP - INICIO */
    public function usuarios() {
        return $this->hasMany('App\Modules\Users\Models\Usuarios', 'pk_usuario_tipo', 'pk_usuario_tipo');
    }
    /* RELATIONSHIP - FIN */

    public function save(array $options = array()) {

        $this['modificacion_pk_usuario'] = Auth::user()->pk_usuario;
        $this['modificacion_fecha'] = date('Y-m-d H:i:s');

        return parent::save($options);
    }

    public function create(array $options = array()) {
        if( $this['pk_usuario_tipo'] === null) {
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
