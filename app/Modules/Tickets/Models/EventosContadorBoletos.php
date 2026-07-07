<?php

namespace App\Modules\Tickets\Models;

use Illuminate\Database\Eloquent\Model;
use Auth;
use DB;

class EventosContadorBoletos extends Model
{
    protected $table = "eventos_contador_boletos";
    public $timestamps = false;
    protected $primaryKey = 'pk_evento_contador_boleto';

    protected $fillable = ['pk_evento','creacion_pk_usuario', 'creacion_fecha'];

    /* RELATIONSHIP - INICIO */
    public function evento() {
        return $this->belongTo('App\Modules\Events\Models\Eventos', 'pk_evento', 'pk_evento');
    }

    public function creacionUsuario() {
        return $this->belongsTo('App\Modules\Users\Models\Usuarios', 'pk_usuario', 'creacion_pk_usuario');
    }
    /* RELATIONSHIP - FIN */


    /*static public function view() {
        $retorno = DB::table('view_eventos_funciones');
        return $retorno;
    }

    static public function viewFind($pk_evento_funcion) {
        $retorno = DB::table('view_eventos_funciones');
        $retorno->where('pk_evento_funcion', '=', $pk_evento_funcion);
        return $retorno->get()->first();
    }*/

    static public function totalIngreso($pk_evento, $pk_funcion) {
        return DB::select(" SELECT
                eventos_localidades.evento_localidad AS localidad,
                count(eventos_contador_boletos.pk_evento_funcion_boleto) AS total
            FROM eventos_contador_boletos
            JOIN eventos_funciones_boletos ON eventos_contador_boletos.pk_evento_funcion_boleto = eventos_funciones_boletos.pk_evento_funcion_boleto
            JOIN eventos_localidades ON eventos_funciones_boletos.pk_evento_localidad = eventos_localidades.pk_evento_localidad
            WHERE eventos_contador_boletos.pk_evento = $pk_evento AND eventos_contador_boletos.pk_evento_funcion = $pk_funcion
            GROUP BY eventos_localidades.evento_localidad

            UNION

            select
                'abonados' as localidad,
                (select count(pk) from bonos_accesos where escaneado = 1) total
            ");
    }

    public function create(array $options = array()) {
        if( $this['pk_evento_contador_boleto'] === null) {
            if($this['creacion_pk_usuario'] === null){
                $this['creacion_pk_usuario'] = Auth::user()->pk_usuario;
            }
            $this['creacion_fecha'] = date('Y-m-d H:i:s');
            return parent::save($options);
        } else {
            return false;
        }
    }

    public function update(array $attributes = array(), array $options = array()) {
        return save($options);
    }
}
