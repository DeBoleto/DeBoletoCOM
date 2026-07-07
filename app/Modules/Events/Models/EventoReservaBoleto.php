<?php

namespace App\Modules\Events\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Tickets\Models\EventosFuncionesBoletos;
use App\Modules\Orders\Models\EventosComprasTipos;
use App\Modules\Events\Models\Eventos;
use App\Modules\Events\Models\EventosLocalidades;
use App\Exceptions\ApplicationException;
use DB;
use Auth;


class EventoReservaBoleto extends Model
{
    protected $table = "eventos_reserva_boleto";
    public $timestamps = false;
    protected $primaryKey = 'pk_evento_reserva_boleto';

    protected $fillable = ['pk_usuario', 'pk_evento', 'pk_evento_funcion', 'pk_seccion', 'asiento', 'creacion_fecha'];

    public function creacionUsuario() {
        return $this->belongsTo('App\Modules\Users\Models\Usuarios', 'pk_usuario', 'creacion_pk_usuario');
    }
    /* RELATIONSHIP - FIN */

    public static function existeReserva($pk_evento, $pk_funcion, $pk_seccion, $asiento, $pk_usuario) {
        $query = (string)"SELECT
                COUNT(*) AS total_cantidad
            FROM
                eventos_reserva_boleto
            WHERE
                pk_evento = $pk_evento
            AND
                pk_evento_funcion = $pk_funcion
            AND
                pk_seccion = '$pk_seccion'
            AND
                pk_usuario !=  '$pk_usuario'
            AND
                asiento = '$asiento'
            and creacion_fecha >= NOW() + INTERVAL 43 MINUTE "
                ;

        // Construir la consulta usando el constructor de consultas de Laravel

        //Log::alert('SQL: '.$query);

        $results = DB::select($query);
        return $results;
    }

    public static function getReservaSesion($pk_evento, $pk_funcion, $pk_seccion) {
        $query = (string)"SELECT
                *
            FROM
                eventos_reserva_boleto
            WHERE
                pk_evento = $pk_evento
            AND
                pk_evento_funcion = $pk_funcion
            AND
                pk_seccion = '$pk_seccion'
            and creacion_fecha >= NOW() + INTERVAL 43 MINUTE "
                ;

        // Construir la consulta usando el constructor de consultas de Laravel

        //Log::alert('SQL: '.$query);

        $results = DB::select($query);
        return $results;
    }

    public static function eliminarReserva($pk_usuario) {

        // Construir la consulta usando el constructor de consultas de Laravel

        //Log::alert('SQL: '.$query);

        EventoReservaBoleto::where('pk_usuario', $pk_usuario)->delete();
    }

    public function save(array $options = array()) {

        // $this['modificacion_pk_usuario'] = Auth::user()->pk_usuario;
        // $this['modificacion_fecha'] = date('Y-m-d H:i:s');
        return parent::save($options);
    }

    public function create($innerTransaction = true) {

        if($innerTransaction) DB::beginTransaction();

        try {
            if( $this['pk_evento_compra_log'] === null) {
                $this['creacion_fecha']            = date('Y-m-d H:i:s');
                $this->save();
                if($innerTransaction) DB::commit();
                return true;
            } else {
                throw new ApplicationException('TICKETS_CREATE_10');
            }
        } catch(ApplicationException $exception) {
            if($innerTransaction) DB::rollBack();
            throw $exception;
        }
    }

    public function update(array $attributes = array(), array $options = array()) {

        try {
            if( $this['pk_evento_reserva_boleto'] != null) {

                $this->pk_evento = (int)$attributes['pk_evento'];
                $this->pk_evento_funcion = (int)$attributes['pk_evento_funcion'];
                $this->asiento = $attributes['asiento'];
                $this->save();
            } else {
                throw new ApplicationException('TICKETS_CREATE_10');
            }
        } catch(ApplicationException $exception) {
            throw $exception;
        }
    }
}
