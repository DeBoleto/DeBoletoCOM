<?php

namespace App\Modules\Venues\Models;

use Illuminate\Database\Eloquent\Model;
use DB;
use Auth;

class Escenarios extends Model
{

    protected $table = "escenarios";
    public $timestamps = false;
    protected $primaryKey = 'pk_escenario';

    protected $fillable = ['pk_ciudad', 'escenario', 'escenario_imagen', 'xml_escenario', 'direccion', 'latitud', 'longitud', 'creacion_pk_usuario', 'creacion_fecha', 'modificacion_pk_usuario', 'modificacion_fecha', 'eliminado'];


    /* RELATIONSHIPS - INICIO */
    public function ciudad() {
        return $this->belongsTo('App\Modules\Venues\Models\Ciudades', 'pk_ciudad', 'pk_ciudad');
    }

    public function creacionUsuario() {
        return $this->belongsTo('App\Modules\Users\Models\Usuarios', 'pk_usuario', 'creacion_pk_usuario');
    }

    public function modificacionUsuario() {
        return $this->belongsTo('App\Modules\Users\Models\Usuarios', 'pk_usuario', 'modificacion_pk_usuario');
    }
    /* RELATIONSHIPS - FIN */


    static public function view() {
        $retorno = DB::table('view_escenarios');
        return $retorno;
    }

    static public function viewFind($pk_escenario) {
        $retorno = DB::table('view_escenarios');
        $retorno->where('pk_escenario', '=', $pk_escenario);
        return $retorno->get()->first();
    }

    public function save(array $options = array()) {

        $this['modificacion_pk_usuario'] = Auth::user()->pk_usuario;
        $this['modificacion_fecha'] = date('Y-m-d H:i:s');

        return parent::save($options);
    }

    public function create(array $options = array()) {
        if( $this['pk_escenario'] === null) {
            $this['creacion_pk_usuario'] = Auth::user()->pk_usuario;
            $this['creacion_fecha'] = date('Y-m-d H:i:s');

            $this['modificacion_pk_usuario'] = Auth::user()->pk_usuario;
            $this['modificacion_fecha'] = date('Y-m-d H:i:s');

            return parent::save($options);
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
