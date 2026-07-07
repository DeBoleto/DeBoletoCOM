<?php

namespace App\Modules\Tickets\Models;

use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use App\Library\FormatValidation;
use App\Library\Encryption;
use App\Library\Errors;
use App\Exceptions\ApplicationException;
use \Exception;
use Illuminate\Support\Facades\DB;
use Auth;


class EventosFuncionesBoletos extends Model
{
    protected $table = "eventos_funciones_boletos";
    public $timestamps = false;
    protected $primaryKey = 'pk_evento_funcion_boleto';

    protected $fillable = [
        'pk_evento', 'pk_evento_funcion', 'pk_evento_localidad', 'pk_evento_periodo_venta',
        'pk_evento_funcion_boleto_status', 'pk_evento_compra', 'asiento', 'precio', 'codigo', 'adquisicion_pk_usuaio',
        'adquisicion_fecha', 'creacion_pk_usuario', 'creacion_fecha', 'modificacion_pk_usuario', 'modificacion_fecha'
    ];

    /* RELATIONSHIP - INICIO */
    public function eventoo() {
        return $this->belongTo('App\Modules\Events\Models\Eventos', 'pk_evento');
    }

    public function eventoFuncion() {
        return $this->belongTo('App\Modules\Events\Models\EventosFunciones', 'pk_evento_funcion', 'pk_evento_funcion');
    }

    public function eventoLocalidad() {
        return $this->belongTo('App\Modules\Events\Models\EventosLocalidades', 'pk_evento_localidad', 'pk_evento_localidad');
    }

    public function eventoPeriodoVenta() {
        return $this->belongTo('App\Modules\Events\Models\EventosPeriodosVentas', 'pk_evento_periodo_venta', 'pk_evento_periodo_venta');
    }

    public function eventoCompra() {
        return $this->belongTo('App\Modules\Orders\Models\EventosCompras', 'pk_evento_compra', 'pk_evento_compra');
    }

    public function creacionUsuario() {
        return $this->belongsTo('App\Modules\Users\Models\Usuarios', 'creacion_pk_usuario', 'pk_usuario');
    }

    public function modificacionUsuario() {
        return $this->belongsTo('App\Modules\Users\Models\Usuarios', 'modificacion_pk_usuario', 'pk_usuario');
    }
    /* RELATIONSHIP - FIN */

    static public function view() {
        $retorno = DB::table('view_eventos_funciones_boletos');
        return $retorno;
    }

    static public function viewFind($pk_evento_funcion_boleto) {
        $retorno = DB::table('view_eventos_funciones_boletos');
        $retorno->where('pk_evento_funcion_boleto', '=', $pk_evento_funcion_boleto);
        return $retorno->get()->first();
    }

    static public  function boletosTotales($pk_evento_compra) {
        $query = (string)"SELECT SUM(precio) as total_boleto,
            eventos_funciones_boletos.pk_evento_funcion_boleto,
            eventos_funciones_boletos.pk_evento,
            eventos_funciones_boletos.pk_evento_compra
        FROM
            eventos_funciones_boletos
        WHERE
            eventos_funciones_boletos.pk_evento_compra = ?
        GROUP BY
            eventos_funciones_boletos.pk_evento_funcion_boleto,
            eventos_funciones_boletos.pk_evento,
            eventos_funciones_boletos.pk_evento_compra";

        $results = DB::select($query, [$pk_evento_compra]);
        return $results;
    }

    static public function totalBoletosCortesia($pk_evento, $pk_evento_funcion) {
        $query = (string)" SELECT
                COUNT(pk_evento_funcion_boleto) as total_boletos_cortesia
            FROM
                eventos_funciones_boletos
            WHERE
                eventos_funciones_boletos.pk_evento = ?
            AND
                eventos_funciones_boletos.pk_evento_funcion = ?
            AND
                eventos_funciones_boletos.pk_evento_funcion_boleto_status != 3
            AND
                eventos_funciones_boletos.cortesia = 1
            GROUP BY
                eventos_funciones_boletos.pk_evento";

        $results = DB::select($query, [$pk_evento, $pk_evento_funcion]);
        return $results;
    }

    static public function totalBoletosVendidos($pk_evento, $pk_evento_funcion) {
        $query = (string)" SELECT
                COUNT(pk_evento_funcion_boleto) as total_boletos_vendidos
            FROM
                eventos_funciones_boletos
            WHERE
                eventos_funciones_boletos.pk_evento = ?
            AND
                eventos_funciones_boletos.pk_evento_funcion = ?
            AND
                eventos_funciones_boletos.pk_evento_funcion_boleto_status = 2
            AND
                eventos_funciones_boletos.cortesia = 0
            GROUP BY
                eventos_funciones_boletos.pk_evento";

        $results = DB::select($query, [$pk_evento, $pk_evento_funcion]);
        return $results;
    }

    static public function desgloseVentas($pk_evento, $pk_evento_funcion) {
        $query = (string)" SELECT
                COUNT(eventos_funciones_boletos.pk_evento_funcion_boleto) AS cantidad_boletos,
                eventos_localidades.evento_localidad,
                eventos_funciones_boletos.cortesia,
                eventos_periodos_ventas.periodo_venta,
                IF(eventos_funciones_boletos.cortesia != 1, eventos_localidades.precio, 0) AS precio_unitario,
                SUM(eventos_funciones_boletos.precio) AS total_boletos
            FROM
                eventos_funciones_boletos
            JOIN eventos_localidades ON eventos_funciones_boletos.pk_evento_localidad = eventos_localidades.pk_evento_localidad
            JOIN eventos_periodos_ventas ON eventos_funciones_boletos.pk_evento_periodo_venta = eventos_periodos_ventas.pk_evento_periodo_venta
            WHERE
                eventos_funciones_boletos.pk_evento = ?
            AND
                eventos_funciones_boletos.pk_evento_funcion = ?
            GROUP BY
                evento_localidad, cortesia";

        $results = DB::select($query, [$pk_evento, $pk_evento_funcion]);
        return $results;
    }

    static public function tablaInformacionGeneral($pk_evento, $pk_evento_funcion, $pk_evento_localidad) {
        $query = (string)" SELECT
            eventos_localidades.evento_localidad,
            (
                SELECT
                    COUNT(cortesia)
                FROM
                    eventos_funciones_boletos
                WHERE
                    cortesia = 0
                AND
                    eventos_compras.pk_evento = ".$pk_evento."
                AND
                    eventos_compras.pk_evento_funcion = ".$pk_evento_funcion."
                AND
                    eventos_funciones_boletos.pk_evento_localidad = ".$pk_evento_localidad."
            ) AS total_boletos_vendidos,
            (
                SELECT
                    COUNT(cortesia)
                FROM
                    eventos_funciones_boletos
                WHERE
                    cortesia = 1
                AND
                    eventos_compras.pk_evento = ".$pk_evento."
                AND
                    eventos_compras.pk_evento_funcion = ".$pk_evento_funcion."
                AND
                    eventos_funciones_boletos.pk_evento_localidad = ".$pk_evento_localidad."
            ) AS total_boletos_cortesia,
            eventos_localidades.precio
        FROM
            eventos_compras
        JOIN eventos_funciones_boletos ON eventos_compras.pk_evento_compra = eventos_funciones_boletos.pk_evento_compra
        JOIN eventos_localidades ON eventos_funciones_boletos.pk_evento_localidad = eventos_localidades.pk_evento_localidad
        WHERE
            eventos_compras.pk_evento = ".$pk_evento."
        AND
            eventos_compras.pk_evento_funcion = ".$pk_evento_funcion."
        AND
            eventos_funciones_boletos.pk_evento_localidad = ".$pk_evento_localidad."
        GROUP BY eventos_compras.pk_evento, eventos_compras.pk_evento_funcion";

        $results = DB::select($query);
        return $results;
    }

    static public function tablaBoletosCortesia($pk_evento, $pk_evento_funcion, $pk_evento_localidad, $fecha_desde = '', $fecha_hasta = '') {
        if(($fecha_desde != '' && $fecha_hasta != '') && ($fecha_desde != $fecha_hasta)) {
            $query = (string)" SELECT
                    COUNT(pk_evento_funcion_boleto) as total_boletos_cortesia
                FROM
                    eventos_funciones_boletos
                WHERE
                    eventos_funciones_boletos.pk_evento = ".$pk_evento."
                AND
                    eventos_funciones_boletos.pk_evento_funcion = ".$pk_evento_funcion."
                AND
                    eventos_funciones_boletos.pk_evento_localidad = ".$pk_evento_localidad."
                AND
                    eventos_funciones_boletos.pk_evento_funcion_boleto_status != 3
                AND
                    eventos_funciones_boletos.cortesia = 1
                AND
                (DATE(eventos_funciones_boletos.adquisicion_fecha) BETWEEN DATE('$fecha_desde') AND DATE('$fecha_hasta'))
                GROUP BY
                    eventos_funciones_boletos.pk_evento";

            $results = DB::select($query);
            return $results;

        } elseif( ($fecha_desde == $fecha_hasta) && ($fecha_desde != '' && $fecha_hasta != '')) {
            $query = (string)" SELECT
                    COUNT(pk_evento_funcion_boleto) as total_boletos_cortesia
                FROM
                    eventos_funciones_boletos
                WHERE
                    eventos_funciones_boletos.pk_evento = ".$pk_evento."
                AND
                    eventos_funciones_boletos.pk_evento_funcion = ".$pk_evento_funcion."
                AND
                    eventos_funciones_boletos.pk_evento_localidad = ".$pk_evento_localidad."
                AND
                    eventos_funciones_boletos.pk_evento_funcion_boleto_status != 3
                AND
                    eventos_funciones_boletos.cortesia = 1
                AND
                    eventos_funciones_boletos.adquisicion_fecha LIKE '$fecha_desde%'
                GROUP BY
                    eventos_funciones_boletos.pk_evento";

            $results = DB::select($query);
            return $results;

        }else {
            $query = (string)" SELECT
                    COUNT(pk_evento_funcion_boleto) as total_boletos_cortesia
                FROM
                    eventos_funciones_boletos
                WHERE
                    eventos_funciones_boletos.pk_evento = ".$pk_evento."
                AND
                    eventos_funciones_boletos.pk_evento_funcion = ".$pk_evento_funcion."
                AND
                    eventos_funciones_boletos.pk_evento_localidad = ".$pk_evento_localidad."
                AND
                    eventos_funciones_boletos.pk_evento_funcion_boleto_status != 3
                AND
                    eventos_funciones_boletos.cortesia = 1
                GROUP BY
                    eventos_funciones_boletos.pk_evento";

            $results = DB::select($query);
            return $results;
        }
    }

    static public function tablaBoletosVendidos($pk_evento, $pk_evento_funcion, $pk_evento_localidad, $punto_venta = null, $fecha_desde = '', $fecha_hasta = '') {
        if(($fecha_desde != '' && $fecha_hasta != '') && ($fecha_desde != $fecha_hasta)) {
            $query = (string)" SELECT
                    eventos_funciones_boletos.precio,
                    sum(eventos_funciones_boletos.comision_deboleto) total_comision,
                    sum(eventos_funciones_boletos.comision_servicio) total_servicio,
                    COUNT(pk_evento_funcion_boleto) as total_boletos_vendidos,
                    ROUND(((eventos_funciones_boletos.precio - eventos_localidades.precio) / eventos_localidades.precio) * 100, 2) * -1 AS porcentaje_diferencia
                FROM
                    eventos_funciones_boletos
                Inner join eventos_localidades  on eventos_localidades.pk_evento_localidad = eventos_funciones_boletos.pk_evento_localidad
                WHERE
                    eventos_funciones_boletos.pk_evento = $pk_evento
                AND
                    eventos_funciones_boletos.pk_evento_funcion = $pk_evento_funcion
                AND
                    eventos_funciones_boletos.pk_evento_localidad = $pk_evento_localidad
                AND
                    eventos_funciones_boletos.pk_evento_funcion_boleto_status = 2 "
                . (($punto_venta != null)? " AND eventos_funciones_boletos.adquisicion_pk_usuario = $punto_venta " : " ") .
                " AND
                    eventos_funciones_boletos.cortesia = 0
                AND
                    (DATE(eventos_funciones_boletos.adquisicion_fecha) BETWEEN DATE('$fecha_desde') AND DATE('$fecha_hasta'))
                GROUP BY
                    eventos_funciones_boletos.precio, eventos_localidades.precio";

            Log::alert('SQL: '.$query);

            $results = DB::select($query);
            return $results;

        } elseif( ($fecha_desde == $fecha_hasta) && ($fecha_desde != '' && $fecha_hasta != '')) {
            $query = (string)" SELECT
                    eventos_funciones_boletos.precio,
                    sum(eventos_funciones_boletos.comision_deboleto) total_comision,
                    sum(eventos_funciones_boletos.comision_servicio) total_servicio,
                    COUNT(pk_evento_funcion_boleto) as total_boletos_vendidos,
                    ROUND(((eventos_funciones_boletos.precio - eventos_localidades.precio) / eventos_localidades.precio) * 100, 2) * -1 AS porcentaje_diferencia
                FROM
                    eventos_funciones_boletos
                Inner join eventos_localidades  on eventos_localidades.pk_evento_localidad = eventos_funciones_boletos.pk_evento_localidad
                WHERE
                    eventos_funciones_boletos.pk_evento = $pk_evento
                AND
                    eventos_funciones_boletos.pk_evento_funcion = $pk_evento_funcion
                AND
                    eventos_funciones_boletos.pk_evento_localidad = $pk_evento_localidad
                AND
                    eventos_funciones_boletos.pk_evento_funcion_boleto_status = 2 "
                    . (($punto_venta != null)? " AND eventos_funciones_boletos.adquisicion_pk_usuario = $punto_venta " : " ") .
                " AND
                    eventos_funciones_boletos.cortesia = 0
                AND
                    eventos_funciones_boletos.adquisicion_fecha LIKE '$fecha_desde%'
                GROUP BY
                    eventos_funciones_boletos.precio, eventos_localidades.precio";

            Log::alert('SQL: '.$query);

            $results = DB::select($query);
            return $results;
        }
        else {
            $query = (string)" SELECT
                    eventos_funciones_boletos.precio,
                    sum(eventos_funciones_boletos.comision_deboleto) total_comision,
                    sum(eventos_funciones_boletos.comision_servicio) total_servicio,
                    COUNT(eventos_funciones_boletos.precio) as total_boletos_vendidos,
                    ROUND(((eventos_funciones_boletos.precio - eventos_localidades.precio) / eventos_localidades.precio) * 100, 2) * -1 AS porcentaje_diferencia
                FROM
                    eventos_funciones_boletos
                Inner join eventos_localidades  on eventos_localidades.pk_evento_localidad = eventos_funciones_boletos.pk_evento_localidad
                WHERE
                    eventos_funciones_boletos.pk_evento = $pk_evento
                AND
                    eventos_funciones_boletos.pk_evento_funcion = $pk_evento_funcion
                AND
                    eventos_funciones_boletos.pk_evento_localidad = $pk_evento_localidad "
                    . (($punto_venta != null)? " AND eventos_funciones_boletos.adquisicion_pk_usuario = $punto_venta " : " ") .
                " AND
                    eventos_funciones_boletos.pk_evento_funcion_boleto_status = 2
                AND
                    eventos_funciones_boletos.cortesia = 0
                GROUP BY
                    eventos_funciones_boletos.precio, eventos_localidades.precio";

            Log::alert('SQL: '.$query);

            $results = DB::select($query);
            return $results;
        }
        return $return;
    }

    static public function tablaCompraOpenpayGeneral($pk_evento, $pk_evento_funcion, $pk_evento_localidad, $precio) {
        $query = (string)" SELECT
                eventos_localidades.pk_evento_localidad,
                eventos_localidades.evento_localidad,
                eventos_localidades.precio,
                count(*) AS cantidad_comprado
            FROM
                eventos_funciones_boletos JOIN eventos_compras ON eventos_funciones_boletos.pk_evento_compra = eventos_compras.pk_evento_compra
            JOIN eventos ON eventos_funciones_boletos.pk_evento = eventos.pk_evento
            JOIN eventos_localidades ON eventos_funciones_boletos.pk_evento_localidad = eventos_localidades.pk_evento_localidad
            WHERE
                eventos_funciones_boletos.pk_evento = $pk_evento
                AND eventos_funciones_boletos.pk_evento_funcion = $pk_evento_funcion
                AND eventos_compras.pk_evento_compra_tipo = 3
                AND eventos_funciones_boletos.pk_evento_localidad = $pk_evento_localidad
                AND eventos_funciones_boletos.precio = $precio
            GROUP BY
                eventos_localidades.pk_evento_localidad,
                eventos_localidades.evento_localidad,
                eventos_localidades.precio";

        $results = DB::select($query);
        return $results;
    }

    static public function tablaTiposCompras($pk_evento, $pk_evento_funcion, $pk_evento_localidad, $punto_venta = null, $pk_evento_compra_tipo = 1, $fecha_desde = '', $fecha_hasta = '', $status = 0, $where_extra = "") {
        if(($fecha_desde != '' && $fecha_hasta != '') && ($fecha_desde != $fecha_hasta)) {
            $query = (string)" SELECT
                    eventos_localidades.evento_localidad,
                    eventos_funciones_boletos.precio,
                    eventos_localidades.comision,
                    sum(eventos_funciones_boletos.comision_deboleto) total_comision,
                    sum(eventos_funciones_boletos.comision_servicio) total_servicio,
                    COUNT(pk_evento_funcion_boleto) AS total_vendidos,
                    eventos_compras.pk_evento_compra_tipo,
                    ROUND(((eventos_funciones_boletos.precio - eventos_localidades.precio) / eventos_localidades.precio) * 100, 2) * -1 AS porcentaje_diferencia
                FROM
                    eventos_funciones_boletos JOIN eventos_compras ON eventos_funciones_boletos.pk_evento_compra = eventos_compras.pk_evento_compra
                    JOIN eventos ON eventos_funciones_boletos.pk_evento = eventos.pk_evento
                    JOIN eventos_localidades ON eventos_funciones_boletos.pk_evento_localidad = eventos_localidades.pk_evento_localidad
                WHERE
                    eventos_funciones_boletos.pk_evento = $pk_evento
                    AND eventos_funciones_boletos.pk_evento_funcion = $pk_evento_funcion "
                    . (($punto_venta != null)? " AND eventos_funciones_boletos.adquisicion_pk_usuario = $punto_venta " : " ") .
                    " AND eventos_compras.pk_evento_compra_tipo = $pk_evento_compra_tipo
                    AND eventos_funciones_boletos.pk_evento_localidad = $pk_evento_localidad
                    AND eventos_funciones_boletos.pk_evento_funcion_boleto_status = 2
                    AND eventos_compras.estatus = $status
                    $where_extra
                    AND (DATE(eventos_funciones_boletos.adquisicion_fecha) BETWEEN DATE('$fecha_desde') AND DATE('$fecha_hasta'))
                GROUP BY eventos_localidades.evento_localidad,
                    eventos_funciones_boletos.precio,
                    eventos_localidades.comision,
                    eventos_localidades.precio,
                    eventos_compras.pk_evento_compra_tipo";

            $return = DB::select($query);

        }
        elseif(($fecha_desde == $fecha_hasta) && ($fecha_desde != '' && $fecha_hasta != '')) {
            $query = (string)" SELECT
                    eventos_localidades.evento_localidad,
                    eventos_funciones_boletos.precio,
                    eventos_localidades.comision,
                    sum(eventos_funciones_boletos.comision_deboleto) total_comision,
                    sum(eventos_funciones_boletos.comision_servicio) total_servicio,
                    COUNT(pk_evento_funcion_boleto) AS total_vendidos,
                    eventos_compras.pk_evento_compra_tipo,
                    ROUND(((eventos_funciones_boletos.precio - eventos_localidades.precio) / eventos_localidades.precio) * 100, 2) * -1 AS porcentaje_diferencia
                FROM
                    eventos_funciones_boletos JOIN eventos_compras ON eventos_funciones_boletos.pk_evento_compra = eventos_compras.pk_evento_compra
                    JOIN eventos ON eventos_funciones_boletos.pk_evento = eventos.pk_evento
                    JOIN eventos_localidades ON eventos_funciones_boletos.pk_evento_localidad = eventos_localidades.pk_evento_localidad
                WHERE
                    eventos_funciones_boletos.pk_evento = $pk_evento
                    AND eventos_funciones_boletos.pk_evento_funcion = $pk_evento_funcion "
                    . (($punto_venta != null)? " AND eventos_funciones_boletos.adquisicion_pk_usuario = $punto_venta " : " ") .
                    " AND eventos_compras.pk_evento_compra_tipo = $pk_evento_compra_tipo
                    AND eventos_funciones_boletos.pk_evento_localidad = $pk_evento_localidad
                    AND eventos_funciones_boletos.pk_evento_funcion_boleto_status = 2
                    AND eventos_compras.estatus = $status
                    $where_extra
                    AND eventos_funciones_boletos.adquisicion_fecha LIKE '$fecha_desde%'
                GROUP BY
                    eventos_localidades.evento_localidad,
                    eventos_funciones_boletos.precio,
                    eventos_localidades.comision,
                    eventos_localidades.precio,
                    eventos_compras.pk_evento_compra_tipo";

            Log::alert('SQL tablaTiposCompras: '.$query);

            $return = DB::select($query);
        }
        else {
            $query = (string)" SELECT
                    eventos_localidades.evento_localidad,
                    eventos_funciones_boletos.precio,
                    eventos_localidades.comision,
                    sum(eventos_funciones_boletos.comision_deboleto) total_comision,
                    sum(eventos_funciones_boletos.comision_servicio) total_servicio,
                    COUNT(pk_evento_funcion_boleto) AS total_vendidos,
                    eventos_compras.pk_evento_compra_tipo,
                    ROUND(((eventos_funciones_boletos.precio - eventos_localidades.precio) / eventos_localidades.precio) * 100, 2) * -1 AS porcentaje_diferencia
                FROM
                    eventos_funciones_boletos JOIN eventos_compras ON eventos_funciones_boletos.pk_evento_compra = eventos_compras.pk_evento_compra
                    JOIN eventos ON eventos_funciones_boletos.pk_evento = eventos.pk_evento
                    JOIN eventos_localidades ON eventos_funciones_boletos.pk_evento_localidad = eventos_localidades.pk_evento_localidad
                WHERE
                    eventos_funciones_boletos.pk_evento = $pk_evento
                    AND eventos_funciones_boletos.pk_evento_funcion = $pk_evento_funcion "
                    . (($punto_venta != null)? " AND eventos_funciones_boletos.adquisicion_pk_usuario = $punto_venta " : " ") .
                    " AND eventos_compras.pk_evento_compra_tipo = $pk_evento_compra_tipo
                    AND eventos_funciones_boletos.pk_evento_localidad = $pk_evento_localidad
                    AND eventos_funciones_boletos.pk_evento_funcion_boleto_status = 2
                    AND eventos_compras.estatus = $status
                    $where_extra
                GROUP BY
                    eventos_localidades.evento_localidad,
                    eventos_funciones_boletos.precio,
                    eventos_localidades.comision,
                    eventos_localidades.precio,
                    eventos_compras.pk_evento_compra_tipo";

            $return = DB::select($query);
        }

        return $return;
    }


    static public function tablaTiposComprasCajas($pk_evento, $pk_evento_funcion, $pk_evento_localidad, $punto_venta = null, $pk_evento_compra_tipo = 1, $fecha_desde = '', $fecha_hasta = '', $status = 0, $where_extra = "") {
        if(($fecha_desde != '' && $fecha_hasta != '') && ($fecha_desde != $fecha_hasta)) {
            $query = (string)" SELECT
                    eventos_localidades.evento_localidad,
                    eventos_funciones_boletos.precio,
                    eventos_localidades.comision,
                    sum(eventos_funciones_boletos.comision_deboleto) total_comision,
                    sum(eventos_funciones_boletos.comision_servicio) total_servicio,
                    COUNT(pk_evento_funcion_boleto) AS total_vendidos,
                    eventos_compras.pk_evento_compra_tipo,
                    ROUND(((eventos_funciones_boletos.precio - eventos_localidades.precio) / eventos_localidades.precio) * 100, 2) * -1 AS porcentaje_diferencia
                FROM
                    eventos_funciones_boletos JOIN eventos_compras ON eventos_funciones_boletos.pk_evento_compra = eventos_compras.pk_evento_compra
                    JOIN eventos ON eventos_funciones_boletos.pk_evento = eventos.pk_evento
                    JOIN eventos_localidades ON eventos_funciones_boletos.pk_evento_localidad = eventos_localidades.pk_evento_localidad
                WHERE
                    eventos_funciones_boletos.pk_evento = $pk_evento
                    AND eventos_funciones_boletos.pk_evento_funcion = $pk_evento_funcion "
                    . (($punto_venta != null)? " AND eventos_funciones_boletos.adquisicion_pk_usuario = $punto_venta " : " ") .
                    " AND eventos_compras.pk_evento_compra_tipo = $pk_evento_compra_tipo
                    AND eventos_funciones_boletos.pk_evento_localidad = $pk_evento_localidad
                    AND eventos_compras.estatus = $status
                    $where_extra
                    AND (DATE(eventos_funciones_boletos.adquisicion_fecha) BETWEEN DATE('$fecha_desde') AND DATE('$fecha_hasta'))
                GROUP BY eventos_localidades.evento_localidad,
                    eventos_funciones_boletos.precio,
                    eventos_localidades.comision,
                    eventos_localidades.precio,
                    eventos_compras.pk_evento_compra_tipo";

            $return = DB::select($query);

        }
        elseif(($fecha_desde == $fecha_hasta) && ($fecha_desde != '' && $fecha_hasta != '')) {
            $query = (string)" SELECT
                    eventos_localidades.evento_localidad,
                    eventos_funciones_boletos.precio,
                    eventos_localidades.comision,
                    sum(eventos_funciones_boletos.comision_deboleto) total_comision,
                    sum(eventos_funciones_boletos.comision_servicio) total_servicio,
                    COUNT(pk_evento_funcion_boleto) AS total_vendidos,
                    eventos_compras.pk_evento_compra_tipo,
                    ROUND(((eventos_funciones_boletos.precio - eventos_localidades.precio) / eventos_localidades.precio) * 100, 2) * -1 AS porcentaje_diferencia
                FROM
                    eventos_funciones_boletos JOIN eventos_compras ON eventos_funciones_boletos.pk_evento_compra = eventos_compras.pk_evento_compra
                    JOIN eventos ON eventos_funciones_boletos.pk_evento = eventos.pk_evento
                    JOIN eventos_localidades ON eventos_funciones_boletos.pk_evento_localidad = eventos_localidades.pk_evento_localidad
                WHERE
                    eventos_funciones_boletos.pk_evento = $pk_evento
                    AND eventos_funciones_boletos.pk_evento_funcion = $pk_evento_funcion "
                    . (($punto_venta != null)? " AND eventos_funciones_boletos.adquisicion_pk_usuario = $punto_venta " : " ") .
                    " AND eventos_compras.pk_evento_compra_tipo = $pk_evento_compra_tipo
                    AND eventos_funciones_boletos.pk_evento_localidad = $pk_evento_localidad
                    AND eventos_compras.estatus = $status
                    $where_extra
                    AND eventos_funciones_boletos.adquisicion_fecha LIKE '$fecha_desde%'
                GROUP BY
                    eventos_localidades.evento_localidad,
                    eventos_funciones_boletos.precio,
                    eventos_localidades.comision,
                    eventos_localidades.precio,
                    eventos_compras.pk_evento_compra_tipo";

            Log::alert('SQL tablaTiposCompras: '.$query);

            $return = DB::select($query);
        }
        else {
            $query = (string)" SELECT
                    eventos_localidades.evento_localidad,
                    eventos_funciones_boletos.precio,
                    eventos_localidades.comision,
                    sum(eventos_funciones_boletos.comision_deboleto) total_comision,
                    sum(eventos_funciones_boletos.comision_servicio) total_servicio,
                    COUNT(pk_evento_funcion_boleto) AS total_vendidos,
                    eventos_compras.pk_evento_compra_tipo,
                    ROUND(((eventos_funciones_boletos.precio - eventos_localidades.precio) / eventos_localidades.precio) * 100, 2) * -1 AS porcentaje_diferencia
                FROM
                    eventos_funciones_boletos JOIN eventos_compras ON eventos_funciones_boletos.pk_evento_compra = eventos_compras.pk_evento_compra
                    JOIN eventos ON eventos_funciones_boletos.pk_evento = eventos.pk_evento
                    JOIN eventos_localidades ON eventos_funciones_boletos.pk_evento_localidad = eventos_localidades.pk_evento_localidad
                WHERE
                    eventos_funciones_boletos.pk_evento = $pk_evento
                    AND eventos_funciones_boletos.pk_evento_funcion = $pk_evento_funcion "
                    . (($punto_venta != null)? " AND eventos_funciones_boletos.adquisicion_pk_usuario = $punto_venta " : " ") .
                    " AND eventos_compras.pk_evento_compra_tipo = $pk_evento_compra_tipo
                    AND eventos_funciones_boletos.pk_evento_localidad = $pk_evento_localidad
                    AND eventos_compras.estatus = $status
                    $where_extra
                GROUP BY
                    eventos_localidades.evento_localidad,
                    eventos_funciones_boletos.precio,
                    eventos_localidades.comision,
                    eventos_localidades.precio,
                    eventos_compras.pk_evento_compra_tipo";

            $return = DB::select($query);
        }

        return $return;
    }

    static public function precioDiferenteEventoBoleto($pk_evento, $pk_evento_funcion, $pk_evento_localidad, $pk_evento_funcion_boleto_status, $precio, $cortesia) {
        $query = (string)"select DISTINCT(precio) precio, pk_evento_localidad, pk_evento_funcion
            from `eventos_funciones_boletos`
            where `pk_evento` = ? and `pk_evento_funcion` = ? and `pk_evento_localidad` = ?
                and `pk_evento_funcion_boleto_status` = ? and `precio` != ? and `cortesia` = ?  ";

        Log::alert('Evento: '.$pk_evento.' Funcion: '.$pk_evento_funcion.' Localidad: '.$pk_evento_localidad);

        $results = DB::select($query, [$pk_evento, $pk_evento_funcion, $pk_evento_localidad, $pk_evento_funcion_boleto_status, $precio, $cortesia]);
        return $results;
    }

    public function save(array $options = array()) {

        $this['modificacion_pk_usuario'] = Auth::user()->pk_usuario;
        $this['modificacion_fecha'] = date('Y-m-d H:i:s');

        return parent::save($options);
    }

    public function save_openpay(array $options = array()) {

        $this['modificacion_fecha'] = date('Y-m-d H:i:s');

        return parent::save($options);
    }

    public function create(array $options = array(), $innerTransaction = true) {

        if($innerTransaction) DB::beginTransaction();

        try {
            if($this['pk_evento_funcion_boleto'] === null)
            {
                $this['creacion_pk_usuario'] = Auth::user()->pk_usuario;
                $this['creacion_fecha'] = date('Y-m-d H:i:s');
                $this['modificacion_pk_usuario'] = Auth::user()->pk_usuario;
                $this['modificacion_fecha'] = date('Y-m-d H:i:s');
                $this->save();

                if($innerTransaction) DB::commit();
                return true;
            }
            else {
                throw new ApplicationException('TICKETS_CREATE_02');
            }
        }
        catch(ApplicationException $exception) {
            if($innerTransaction) DB::rollBack();
            throw $exception;
        }

    }

    public function create_openpay(array $options = array(), $innerTransaction = true) {

        if($innerTransaction) DB::beginTransaction();

        try {
            if($this['pk_evento_funcion_boleto'] === null)
            {
                $this['creacion_fecha'] = date('Y-m-d H:i:s');
                $this['modificacion_fecha'] = date('Y-m-d H:i:s');
                $this->save_openpay();

                if($innerTransaction) DB::commit();
                return true;
            }
            else {
                throw new ApplicationException('TICKETS_CREATE_02');
            }
        }
        catch(ApplicationException $exception) {
            if($innerTransaction) DB::rollBack();
            throw $exception;
        }

    }

    public function update(array $attributes = array(), array $options = array()) {
        return save($options);
    }

    static public function getAsientosCompra(int $pkEventoCompra){
        $query = (string)" SELECT
            eventos.evento,
            eventos_localidades.evento_localidad localidad,
            eventos_funciones_boletos.asiento,
            eventos_funciones_boletos.precio
        FROM eventos_funciones_boletos
            JOIN eventos_compras ON eventos_funciones_boletos.pk_evento_compra = eventos_compras.pk_evento_compra
            JOIN eventos ON eventos_funciones_boletos.pk_evento = eventos.pk_evento
            JOIN eventos_localidades ON eventos_funciones_boletos.pk_evento_localidad = eventos_localidades.pk_evento_localidad
        WHERE
            eventos_funciones_boletos.pk_evento_compra =  $pkEventoCompra ";

        $results = DB::select($query);
        return $results;
    }

    static public function totalCompraBoletos(int $pkEvento){
        $query = (string)" SELECT
            SUM(eventos_funciones_boletos.precio) as total
        FROM eventos_funciones_boletos
        WHERE
            eventos_funciones_boletos.pk_evento =  $pkEvento
            AND eventos_funciones_boletos.pk_evento_funcion_boleto_status in (2,4)";

        $results = DB::select($query);
        return $results;
    }

    static public function tablaTiposComprasTD($pk_evento, $pk_evento_funcion, $pk_evento_localidad, $punto_venta = null, $pk_evento_compra_tipo = 1, $fecha_desde = '', $fecha_hasta = '', $status = 0, $where_extra = "") {
        if(($fecha_desde != '' && $fecha_hasta != '') && ($fecha_desde != $fecha_hasta)) {
            $query = (string)" SELECT
                    eventos_localidades.evento_localidad,
                    eventos_funciones_boletos.precio,
                    eventos_localidades.comision,
                    COUNT(pk_evento_funcion_boleto) AS total_vendidos,
                    eventos_compras.pk_evento_compra_tipo,
                    ROUND(((eventos_funciones_boletos.precio - eventos_localidades.precio) / eventos_localidades.precio) * 100, 2) * -1 AS porcentaje_diferencia
                FROM
                    eventos_funciones_boletos JOIN eventos_compras ON eventos_funciones_boletos.pk_evento_compra = eventos_compras.pk_evento_compra
                    JOIN eventos ON eventos_funciones_boletos.pk_evento = eventos.pk_evento
                    JOIN eventos_localidades ON eventos_funciones_boletos.pk_evento_localidad = eventos_localidades.pk_evento_localidad
                WHERE
                    eventos_funciones_boletos.pk_evento = $pk_evento
                    AND eventos_funciones_boletos.pk_evento_funcion = $pk_evento_funcion
                    AND eventos_compras.total_defender > 0"
                . (($punto_venta != null)? " AND eventos_funciones_boletos.adquisicion_pk_usuario = $punto_venta " : " ") .
                " AND eventos_compras.pk_evento_compra_tipo = $pk_evento_compra_tipo
                    AND eventos_funciones_boletos.pk_evento_localidad = $pk_evento_localidad
                    AND eventos_funciones_boletos.pk_evento_funcion_boleto_status = 2
                    AND eventos_compras.estatus = $status
                    $where_extra
                    AND (DATE(eventos_funciones_boletos.adquisicion_fecha) BETWEEN DATE('$fecha_desde') AND DATE('$fecha_hasta'))
                GROUP BY eventos_localidades.evento_localidad,
                    eventos_funciones_boletos.precio,
                    eventos_localidades.comision,
                    eventos_localidades.precio,
                    eventos_compras.pk_evento_compra_tipo";

            $return = DB::select($query);

        }
        elseif(($fecha_desde == $fecha_hasta) && ($fecha_desde != '' && $fecha_hasta != '')) {
            $query = (string)" SELECT
                    eventos_localidades.evento_localidad,
                    eventos_funciones_boletos.precio,
                    eventos_localidades.comision,
                    COUNT(pk_evento_funcion_boleto) AS total_vendidos,
                    eventos_compras.pk_evento_compra_tipo,
                    ROUND(((eventos_funciones_boletos.precio - eventos_localidades.precio) / eventos_localidades.precio) * 100, 2) * -1 AS porcentaje_diferencia
                FROM
                    eventos_funciones_boletos JOIN eventos_compras ON eventos_funciones_boletos.pk_evento_compra = eventos_compras.pk_evento_compra
                    JOIN eventos ON eventos_funciones_boletos.pk_evento = eventos.pk_evento
                    JOIN eventos_localidades ON eventos_funciones_boletos.pk_evento_localidad = eventos_localidades.pk_evento_localidad
                WHERE
                    eventos_funciones_boletos.pk_evento = $pk_evento
                    AND eventos_funciones_boletos.pk_evento_funcion = $pk_evento_funcion
                    AND eventos_compras.total_defender > 0"
                . (($punto_venta != null)? " AND eventos_funciones_boletos.adquisicion_pk_usuario = $punto_venta " : " ") .
                " AND eventos_compras.pk_evento_compra_tipo = $pk_evento_compra_tipo
                    AND eventos_funciones_boletos.pk_evento_localidad = $pk_evento_localidad
                    AND eventos_funciones_boletos.pk_evento_funcion_boleto_status = 2
                    AND eventos_compras.estatus = $status
                    $where_extra
                    AND eventos_funciones_boletos.adquisicion_fecha LIKE '$fecha_desde%'
                GROUP BY
                    eventos_localidades.evento_localidad,
                    eventos_funciones_boletos.precio,
                    eventos_localidades.comision,
                    eventos_localidades.precio,
                    eventos_compras.pk_evento_compra_tipo";

            Log::alert('SQL tablaTiposCompras: '.$query);

            $return = DB::select($query);
        }
        else {
            $query = (string)" SELECT
                    eventos_localidades.evento_localidad,
                    eventos_funciones_boletos.precio,
                    eventos_localidades.comision,
                    COUNT(pk_evento_funcion_boleto) AS total_vendidos,
                    SUM(eventos_compras.total_defender) AS total_defender,
                    eventos_compras.pk_evento_compra_tipo,
                    ROUND(((eventos_funciones_boletos.precio - eventos_localidades.precio) / eventos_localidades.precio) * 100, 2) * -1 AS porcentaje_diferencia
                FROM
                    eventos_funciones_boletos JOIN eventos_compras ON eventos_funciones_boletos.pk_evento_compra = eventos_compras.pk_evento_compra
                    JOIN eventos ON eventos_funciones_boletos.pk_evento = eventos.pk_evento
                    JOIN eventos_localidades ON eventos_funciones_boletos.pk_evento_localidad = eventos_localidades.pk_evento_localidad
                WHERE
                    eventos_funciones_boletos.pk_evento = $pk_evento
                    AND eventos_funciones_boletos.pk_evento_funcion = $pk_evento_funcion
                    AND eventos_compras.total_defender > 0"
                . (($punto_venta != null)? " AND eventos_funciones_boletos.adquisicion_pk_usuario = $punto_venta " : " ") .
                " AND eventos_compras.pk_evento_compra_tipo = $pk_evento_compra_tipo
                    AND eventos_funciones_boletos.pk_evento_localidad = $pk_evento_localidad
                    AND eventos_funciones_boletos.pk_evento_funcion_boleto_status = 2
                    AND eventos_compras.estatus = $status
                    $where_extra
                GROUP BY
                    eventos_localidades.evento_localidad,
                    eventos_funciones_boletos.precio,
                    eventos_localidades.comision,
                    eventos_localidades.precio,
                    eventos_compras.pk_evento_compra_tipo";

            $return = DB::select($query);
        }

        return $return;
    }


    static public function bonosEscaneoAcceso(string $codigo){
        $query = (string)"SELECT ba.* FROM deboleto.bonos_accesos AS ba where ba.folio = '$codigo'";

        Log::alert('SQL SCAN: '.$query);

        $results = DB::select($query);
        return $results;
    }

    static public function bonosEscaneoValidacion(string $codigo){
        $query = (string)"SELECT ba.* FROM deboleto.bonos_accesos AS ba where ba.folio = '$codigo' and escaneado = false";

        Log::alert('SQL SCAN: '.$query);

        $results = DB::select($query);
        return $results;
    }

}
