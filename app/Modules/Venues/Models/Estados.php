<?php

namespace App\Modules\Venues\Models;

use Illuminate\Database\Eloquent\Model;
use DB;
use Auth;

class Estados extends Model
{
    protected $table = "estados";
    public $timestamps = false;
    protected $primaryKey = 'pk_estado';

    protected $fillable = ['estado', 'creacion_pk_usuario', 'creacion_fecha', 'modificacion_pk_usuario', 'modificacion_fecha', 'eliminado'];

    /* RELATIONSHIPS - INICIO */
    public function creacionUsuario() {
        return $this->belongsTo('App\Modules\Users\Models\Usuarios', 'pk_usuario', 'creacion_pk_usuario');
    }

    public function modificacionUsuario() {
        return $this->belongsTo('App\Modules\Users\Models\Usuarios', 'pk_usuario', 'modificacion_pk_usuario');
    }

    public function ciudades() {
        return $this->hasMany('App\Modules\Venues\Models\Ciudades', 'pk_estado', 'pk_estado');
    }
    /* RELATIONSHIPS - FIN */

    public function save(array $options = array()) {

        $this['modificacion_pk_usuario'] = Auth::user()->pk_usuario;
        $this['modificacion_fecha'] = date('Y-m-d H:i:s');

        return parent::save($options);
    }

    public function create(array $options = array()) {
        if( $this['pk_estado'] === null) {
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
