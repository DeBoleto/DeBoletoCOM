<?php

namespace App\Modules\Users\Models;

use Illuminate\Database\Eloquent\Model;
use DB;
use Auth;

class Privilegios extends Model
{
    protected $table = "privilegios";
    public $timestamps = false;
    protected $primaryKey = 'pk_privilegio';

    protected $fillable = ['pk_privilegio_categoria', 'padre_pk_privilegio', 'privilegio', 'etiqueta', 'menu', 'menu_orden', 'menu_url', 'activo'];


    /* RELATIONSHIPS - INICIO */
    public function privilegioCategoria() {
        return $this->belongsTo('App\Modules\Users\Models\PrivilegiosCategorias', 'pk_privilegio_categoria', 'pk_privilegio_categoria');
    }
    /* RELATIONSHIPS - FIN */

    static public function view() {
        $retorno = DB::table('view_privilegios');
        return $retorno;
    }

    static public function viewFind($pk_privilegio) {
        $retorno = DB::table('view_privilegios');
        $retorno->where('pk_privilegio', '=', $pk_privilegio);
        return $retorno->get()->first();
    }

    public function save(array $options = array()) {
        $this['modificacion_pk_usuario'] = Auth::user()->pk_usuario;
        $this['modificacion_fecha'] = date('Y-m-d H:i:s');

        return parent::save($options);
    }

    public function create(array $options = array()) {
        if( $this['pk_privilegio'] === null) {
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
