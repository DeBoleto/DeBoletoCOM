<?php

namespace App\Modules\Users\Models;

use Illuminate\Database\Eloquent\Model;
use DB;
use Auth;

class UsuariosDocumentos extends Model
{
    protected $table = "usuarios_documentos";
    public $timestamps = false;
    protected $primaryKey = 'pk_usuario_documento';

    protected $fillable = ['pk_usuario', 'documento', 'descripcion', 'fichero', 'privado', 'notificar', 'vigencia', 'expedicion_fecha', 'vencimiento_fecha', 'creacion_pk_usuario', 'creacion_fecha', 'modificacion_pk_usuario', 'modificacion_fecha', 'eliminado'];

    /* RELATIONSHIPS - INICIO */
    public function usuario() {
        return $this->belongsTo('App\Modules\Users\Models\Usuarios', 'pk_usuario', 'pk_usuario');
    }

    public function creacionUsuario() {
        return $this->belongsTo('App\Modules\Users\Models\Usuarios', 'pk_usuario', 'creacion_pk_usuario');
    }

    public function modificacionUsuario() {
        return $this->belongsTo('App\Modules\Users\Models\Usuarios', 'pk_usuario', 'modificacion_pk_usuario');
    }
    /* RELATIONSHIPS - FIN */


    static public function view() {
        $retorno = DB::table('view_usuarios_documentos');
        return $retorno;
    }

    static public function viewFind($pk_usuario) {
        $retorno = DB::table('view_usuarios_documentos');
        $retorno->where('pk_usuario', '=', $pk_usuario);
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
