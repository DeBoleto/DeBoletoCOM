<?php

namespace App\Modules\Events\Models;

use Illuminate\Database\Eloquent\Model;
use DB;
use Auth;

class EventosLocalidades extends Model
{
    protected $table = "eventos_localidades";
    public $timestamps = false;
    protected $primaryKey = 'pk_evento_localidad';

    protected $fillable = [
        'pk_evento', 'pk_evento_periodo_venta', 'evento_localidad', 'precio', 'creacion_pk_usuario', 'creacion_fecha', 'modificacion_pk_usuario',
        'modificacion_fecha', 'eliminado', 'promocion_porcentaje', 'promocion'
    ];


    /* RELATIONSHIP - INICIO */
    public function evento() {
        return $this->belongTo('App\Modules\Events\Models\Eventos', 'pk_evento', 'pk_evento');
    }

    public function promocion()
    {
        return $this->hasOne('App\Modules\Events\Models\PromocionEventoLocalidades', 'pk_evento_localidad');
    }

    public function creacionUsuario() {
        return $this->belongsTo('App\Modules\Users\Models\Usuarios', 'pk_usuario', 'creacion_pk_usuario');
    }

    public function modificacionUsuario() {
        return $this->belongsTo('App\Modules\Users\Models\Usuarios', 'pk_usuario', 'modificacion_pk_usuario');
    }

    public function localidadAsientos() {
        return $this->hasOne('App\Modules\Events\Models\EventoLocalidadAsiento', 'pk_evento_localidad');
    }
    /* RELATIONSHIP - FIN */

    static public function view() {
        $retorno = DB::table('view_eventos_localidades');
        return $retorno;
    }

    static public function viewFind($pk_evento_localidad) {
        $retorno = DB::table('view_eventos_localidades');
        $retorno->where('pk_evento_localidad', '=', $pk_evento_localidad);
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
