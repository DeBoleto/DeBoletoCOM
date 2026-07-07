<?php

namespace App\Modules\Tickets\Models;

use Illuminate\Database\Eloquent\Model;
use \Illuminate\Support\Facades\DB;
use Auth;


class EventoEstacionamientoBoleto extends Model
{
    protected $table = "eventos_estacionamiento_boleto";
    public $timestamps = false;
    protected $primaryKey = 'pk_eventos_estacionamiento_boleto';

    protected $fillable = [
        'pk_evento_compra', 'lugar', 'precio', 'asistencia'
    ];


    public function save(array $options = array()) {

        // $this['modificacion_pk_usuario'] = Auth::user()->pk_usuario;
        // $this['modificacion_fecha'] = date('Y-m-d H:i:s');
        return parent::save($options);
    }

    public function create($innerTransaction = true) {

        if($innerTransaction) DB::beginTransaction();

        try {
            if( $this['pk_eventos_estacionamiento_boleto'] === null) {
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
            if( $this['pk_eventos_estacionamiento_boleto'] != null) {
                $this->save();
            } else {
                throw new ApplicationException('TICKETS_CREATE_10');
            }
        } catch(ApplicationException $exception) {
            throw $exception;
        }
    }

    public static function count_boletos($pk_evento){
        $query = (string)"
            SELECT
                count(*) as total
            FROM
                eventos_estacionamiento_boleto
            WHERE
                pk_evento = $pk_evento";

        // Construir la consulta usando el constructor de consultas de Laravel

        //Log::alert('SQL: '.$query);

        $results = DB::select($query);
        return $results;
    }



    static public function totalBoletosVendidos($pk_evento) {
        $query = " SELECT
                COUNT(pk_eventos_estacionamiento_boleto) as total_boletos_vendidos
            FROM
                eventos_estacionamiento_boleto
            WHERE
                pk_evento = ?
            GROUP BY
                pk_evento";

        // Construir la consulta usando el constructor de consultas de Laravel

        //Log::alert('SQL: '.$query);

        $results = DB::select($query, [$pk_evento,]);
        return $results;
    }

    static public function totalBoletosVendidosLocalidad($pk_evento) {
        $query = "
                select el.evento_localidad, COUNT(DISTINCT(eeb.pk_eventos_estacionamiento_boleto)) total
                from eventos_estacionamiento_boleto eeb
                inner join eventos_compras ec on ec.pk_evento_compra = eeb.pk_evento_compra
                inner join eventos_funciones_boletos efb on efb.pk_evento_compra  = ec.pk_evento_compra
                inner join eventos_localidades el on el.pk_evento_localidad = efb.pk_evento_localidad
                where eeb.pk_evento = ?
                GROUP by el.evento_localidad
                ORDER by el.pk_evento_localidad";

        // Construir la consulta usando el constructor de consultas de Laravel

        //Log::alert('SQL: '.$query);

        $results = DB::select($query, [$pk_evento,]);
        return $results;
    }
}
