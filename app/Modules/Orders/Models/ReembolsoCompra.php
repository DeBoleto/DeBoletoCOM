<?php

namespace App\Modules\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use DB;
use Auth;

class ReembolsoCompra extends Model
{
    protected $table = "reembolso_compra";
    public $timestamps = false;
    protected $primaryKey = 'pk_reembolso_compra';

    protected $fillable = [
        'pk_evento_compra', 'ine', 'boletos', 'no_cuenta', 'clabe', 'creacion_fecha', 'banco', 'otros', 'estatus',
        'evidencia'
    ];

    public function creacionUsuario() {
        return $this->belongsTo('App\Modules\Users\Models\Usuarios', 'pk_usuario', 'creacion_pk_usuario');
    }

    public function save(array $options = array()) {
        return parent::save($options);
    }

    public function create($innerTransaction = true) {
        if($innerTransaction) DB::beginTransaction();
        try {
            if( $this['pk_reembolso_compra'] === null) {
                $this['creacion_fecha']            = date('Y-m-d H:i:s');
                $this->save();
                if($innerTransaction) DB::commit();
                return true;
            } else {
                throw new \App\Exceptions\ApplicationException('TICKETS_CREATE_10');
            }
        } catch(\App\Exceptions\ApplicationException $exception) {
            if($innerTransaction) DB::rollBack();
            throw $exception;
        }
    }

    public function update(array $attributes = array(), array $options = array()) {
        try {
            if( $this['pk_reembolso_compra'] != null) {
                $this->save();
            } else {
                throw new \App\Exceptions\ApplicationException('TICKETS_CREATE_10');
            }
        } catch(\App\Exceptions\ApplicationException $exception) {
            throw $exception;
        }
    }

    static public function reembolsoSolicitud($pk_evento) {
        $query = (string)" select rc.pk_reembolso_compra, ec.pk_evento_compra,
            e.evento, COUNT(efb.pk_evento_funcion_boleto) boletos_total, SUM(efb.precio) total,
	        rc.estatus, rc.boletos, rc.ine, rc.evidencia, rc.banco, rc.otros, rc.no_cuenta, rc.tarjeta, rc.email,
	        rc.telefono
        from reembolso_compra rc
        inner join eventos_compras ec on ec.pk_evento_compra = rc.pk_evento_compra
        inner join eventos e on e.pk_evento = ec.pk_evento
        inner join eventos_funciones_boletos efb on efb.pk_evento_compra = ec.pk_evento_compra
        where e.pk_evento = ?
        group by rc.pk_reembolso_compra, ec.pk_evento_compra, e.evento,
            rc.estatus, rc.boletos, rc.ine, rc.evidencia, rc.banco, rc.otros, rc.no_cuenta, rc.tarjeta, rc.email,
	        rc.telefono";

        $results = DB::select($query, [$pk_evento]);
        return $results;
    }

    static public function reembolsosCompras($pk_evento)
    {
        $query = (string)"select ec.pk_evento_compra, e.evento, COUNT(efb.pk_evento_funcion_boleto) boletos, SUM(efb.precio) total,
        (select rc.pk_reembolso_compra from reembolso_compra rc where rc.pk_evento_compra = ec.pk_evento_compra limit 1) pk_reembolso_compra, ec.estatus,
        CASE ec.pk_evento_compra_tipo
            WHEN 1 THEN 'Efectivo'
            WHEN 2 THEN 'Tarjeta'
            ELSE 'Web'
        END tipo_compra, ec.tarjeta_numero tarjeta,  u.correo, ec.tarjeta_banco banco
        from eventos_compras ec
        inner join eventos e on e.pk_evento = ec.pk_evento
        inner join eventos_funciones_boletos efb on efb.pk_evento_compra = ec.pk_evento_compra
        inner join usuarios u on u.pk_usuario = ec.pk_usuario
        where ec.pk_evento = ?
        group by pk_reembolso_compra, ec.pk_evento_compra, ec.estatus, e.evento, ec.pk_evento_compra_tipo, ec.tarjeta_numero,  u.correo, ec.tarjeta_banco
        order by pk_reembolso_compra; ";

        $results = DB::select($query, [$pk_evento]);
        return $results;
    }

    static public function totalReembolsoCompras($pk_evento)
    {
        $query = (string)"select SUM(efb.precio) total
            from eventos_compras ec
            inner join eventos_funciones_boletos efb on efb.pk_evento_compra = ec.pk_evento_compra
            where ec.pk_evento = ?;";

        $results = DB::select($query, [$pk_evento]);
        return $results;
    }

    static public function totalReembolsadoCompras($pk_evento)
    {
        $query = (string)"
            select SUM(efb.precio) total
            from reembolso_compra rc
            inner join eventos_compras ec on ec.pk_evento_compra =rc.pk_evento_compra
            inner join eventos_funciones_boletos efb on efb.pk_evento_compra = ec.pk_evento_compra
            where ec.pk_evento = ?;";

        $results = DB::select($query, [$pk_evento]);
        return $results;
    }

    static public function reembolsoDetail($pk){
        $query = (string)"
            Select rc.no_cuenta, rc.clabe, rc.tarjeta, rc.email, rc.telefono,
            CASE rc.banco
                WHEN 1 THEN 'BBVA Bancomer'
                WHEN 2 THEN 'HSBC'
                WHEN 4 THEN 'ScotiaBank'
                WHEN 5 THEN 'Santander'
                ELSE null
            END banco_rc
            from reembolso_compra rc
            where rc.pk_reembolso_compra = ?
        ";

        $results = DB::select($query, [$pk]);
        return $results;
    }
}
