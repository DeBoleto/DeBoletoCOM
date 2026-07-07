<?php

namespace App\Modules\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Tickets\Models\EventosFuncionesBoletos;
use App\Modules\Orders\Models\EventosComprasTipos;
use App\Modules\Events\Models\Eventos;
use App\Modules\Events\Models\EventosLocalidades;
use DB;
use Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class EventosCompras extends Model
{
    protected $table = "eventos_compras";
    public $timestamps = false;
    protected $primaryKey = 'pk_evento_compra';

    protected $fillable = [
        'pk_usuario', 'total_compra', 'total_comision_aplicacion', 'total_comision_pago_linea', 'total_utilidad',
        'creacion_pk_usuario', 'creacion_fecha', 'pk_openpay', 'estatus', 'pk_corte_caja'
    ];

    public function eventosFuncionesBoletos() {
        return $this->hasMany('App\Modules\Tickets\Models\EventosFuncionesBoletos', 'pk_evento_compra', 'pk_evento_compra');
    }

    public function creacionUsuario() {
        return $this->belongsTo('App\Modules\Users\Models\Usuarios', 'pk_usuario', 'creacion_pk_usuario');
    }

    public function corteCaja() {
        return $this->belongsTo('App\Modules\BoxOffice\Models\CortesCajas', 'pk_corte_caja', 'pk_corte_caja');
    }

    public static function viewComprasUsuario($pk_usuario) {
        $retorno = DB::table('view_eventos_compras');
        $retorno->where('pk_usuario', '=', $pk_usuario);
        $retorno->orderBy('creacion_fecha', 'DESC');
        $retorno->limit(50);
        return $retorno->get();
    }

    public static function cantidadBoletosTipo($pk_evento, $pk_funcion, $tipo_compra = 1, $estatus=0) {
        $query = (string)"SELECT
                eventos_compras_tipos.evento_compra_tipo,
                COUNT(eventos_funciones_boletos.pk_evento_funcion_boleto) AS total_cantidad,
                SUM(eventos_funciones_boletos.precio) total
            FROM
                eventos_compras
            JOIN eventos_funciones_boletos ON eventos_compras.pk_evento_compra = eventos_funciones_boletos.pk_evento_compra
            JOIN eventos_compras_tipos ON eventos_compras.pk_evento_compra_tipo = eventos_compras_tipos.pk_evento_compra_tipo
            WHERE
                eventos_compras.pk_evento = $pk_evento
            AND
                eventos_compras.pk_evento_funcion = $pk_funcion
            AND
                eventos_compras.estatus = $estatus
            AND
                eventos_funciones_boletos.cortesia = 0
            AND
                eventos_funciones_boletos.pk_evento_funcion_boleto_status = 2
            AND
                eventos_compras.pk_evento_compra_tipo = $tipo_compra
            GROUP bY
                eventos_compras_tipos.evento_compra_tipo";

        $results = DB::select($query);
        return $results;
    }

    public static function totalBoletosTipo($pk_evento, $pk_funcion, $tipo_compra = 1, $fecha_desde = '', $fecha_hasta = '') {
        $totalCompra = 0;
        if ($tipo_compra != 3 && $tipo_compra != 7) {
            $query = " SELECT
                        sum(eventos_funciones_boletos.precio) total_compra
                    FROM
                        eventos_compras
                    JOIN eventos_funciones_boletos
                        ON eventos_funciones_boletos.pk_evento_compra = eventos_compras.pk_evento_compra
                    WHERE
                        eventos_compras.pk_evento = $pk_evento
                    AND
                        eventos_compras.pk_evento_funcion = $pk_funcion
                    AND
                        eventos_funciones_boletos.pk_evento_funcion_boleto_status = 2
                    AND
                        eventos_compras.pk_evento_compra_tipo = $tipo_compra";

            Log::alert('SQL: '.$query);
            $return = DB::select($query);
        }
        elseif($tipo_compra == 7) {
            Log::info("Tipo compra: ".$tipo_compra);
            $query = " SELECT
                        SUM(eventos_compras.total_compra) AS total_compra
                    FROM
                        eventos_compras
                    JOIN eventos_funciones_boletos
                        ON eventos_funciones_boletos.pk_evento_compra = eventos_compras.pk_evento_compra
                    WHERE
                        eventos_compras.pk_evento = $pk_evento
                        and eventos_compras.estatus = 4
                    AND
                        eventos_compras.pk_evento_funcion = $pk_funcion
                    AND
                        eventos_funciones_boletos.pk_evento_funcion_boleto_status = 2
                    AND
                        eventos_compras.pk_evento_compra_tipo = 3";

            Log::alert('SQL: '.$query);
            $return = DB::select($query);
        }
        else {
            $sql = " SELECT
                        SUM(eventos_funciones_boletos.precio) AS total_compra
                    FROM
                        eventos_compras
                    JOIN eventos_funciones_boletos
                        ON eventos_funciones_boletos.pk_evento_compra = eventos_compras.pk_evento_compra
                    WHERE
                        eventos_compras.pk_evento = $pk_evento
                        and eventos_compras.estatus = 0
                    AND
                        eventos_compras.pk_evento_funcion = $pk_funcion
                    AND
                        eventos_funciones_boletos.pk_evento_funcion_boleto_status = 2
                    AND
                        eventos_compras.pk_evento_compra_tipo = $tipo_compra";
            Log::alert('SQL: ' . $sql);
            $return = DB::select($sql);
        }
        return $return;
    }

    public static function totalComisionAplicacion($pk_evento, $pk_funcion) {
        $totalComisionAplicacion = 0;
        $compraFinalizada = DB::select(" SELECT
                                                eventos_funciones_boletos.pk_evento_compra,
                                                eventos_compras.total_comision_aplicacion AS total_comision
                                            FROM
                                                eventos_compras
                                            JOIN eventos_funciones_boletos
                                                ON eventos_funciones_boletos.pk_evento_compra = eventos_compras.pk_evento_compra
                                            WHERE
                                                eventos_compras.pk_evento = $pk_evento
                                            AND
                                                eventos_funciones_boletos.pk_evento_funcion_boleto_status = 2
                                            AND
                                                eventos_compras.pk_evento_funcion = $pk_funcion");

        foreach ($compraFinalizada as $compra) {
            $totalComisionAplicacion = $totalComisionAplicacion + $compra->total_comision;
        }

        return ['total_comision' => $totalComisionAplicacion];
    }

    public static function totalComisionTarjetas($pk_evento, $pk_funcion) {
        $query = (string)"SELECT
                eventos_funciones_boletos.pk_evento_compra,
                eventos_compras.total_comision_tarjeta AS total_comision
            FROM
                eventos_compras
            JOIN eventos_funciones_boletos
                ON eventos_funciones_boletos.pk_evento_compra = eventos_compras.pk_evento_compra
            WHERE
                eventos_compras.pk_evento = $pk_evento
            AND
                eventos_funciones_boletos.pk_evento_funcion_boleto_status = 2
            AND
                eventos_compras.pk_evento_funcion = $pk_funcion";

        $compraFinalizada = DB::select($query);
        $totalComisionTarjeta = 0;
        foreach ($compraFinalizada as $compra) {
            $totalComisionTarjeta = $totalComisionTarjeta + $compra->total_comision;
        }

        return ['total_comision' => $totalComisionTarjeta];
    }

    public static function totalComisionOpenpay($pk_evento, $pk_funcion, $estatus=0) {
        $query = (string)"SELECT
                SUM(eventos_compras.total_comision_pago_linea) AS total_comision
            FROM
                eventos_compras
            WHERE
                eventos_compras.pk_evento = $pk_evento
                and eventos_compras.estatus = $estatus
            AND
                eventos_compras.pk_evento_funcion = $pk_funcion";

        $results = DB::select($query);
        return $results;
    }

    public static function existeOpenpayCompra($pk_openpay) {
        $query = (string)"SELECT
                COUNT(*) AS total
            FROM
                eventos_compras
            WHERE
                eventos_compras.pk_openpay  = '$pk_openpay'";

        $results = DB::select($query);
        return $results;
    }

    public static function cantidadCompras($pk_evento, $pk_funcion, $pk_localidad, $precio, $tipo_compra = 3) {
        $query = (string)" SELECT
                eventos_compras.pk_evento_compra as compra
            FROM
                eventos_compras
                JOIN eventos_funciones_boletos on eventos_compras.pk_evento_compra = eventos_funciones_boletos.pk_evento_compra
            WHERE
                eventos_compras.pk_evento = $pk_evento
            AND
                eventos_compras.pk_evento_funcion = $pk_funcion
            AND
                eventos_compras.pk_evento_compra_tipo = $tipo_compra
            AND
                eventos_funciones_boletos.pk_evento_localidad = $pk_localidad
            AND
                eventos_funciones_boletos.precio = $precio
            GROUP BY eventos_compras.pk_evento_compra";

        $results = DB::select($query);
        return $results;
    }

    public static function ResumenVentaIndexBoxOffice($usuario=0) {
        $query = (string)"
                select DISTINCT(ec.pk_evento) pk_evento, e.evento, e.imagen, c.ciudad, e2.estado, date_format(e.inicio_fecha, '%d/%m/%Y') inicio_fecha_natural,
                    format(sum(efb.precio), 2) total_venta_natural, format(sum(0), 2) total_venta,
                    format(sum(0), 2) total_utilidad
                from eventos_compras ec
                inner join eventos e on e.pk_evento = ec.pk_evento
                inner join escenarios es On es.pk_escenario = e.pk_escenario
                inner join ciudades c on c.pk_ciudad = es.pk_ciudad
                inner join estados e2  on e2.pk_estado = c.pk_estado
                inner join eventos_funciones_boletos efb on efb.pk_evento_compra = ec.pk_evento_compra
                where e.pk_evento_status in (1, 2) and e.pk_evento_tipo = 1
                GROUP by ec.pk_evento, e.evento, e.imagen, c.ciudad, e2.estado,  e.inicio_fecha
                order by e.inicio_fecha DESC
                limit 5";
        if($usuario != 0){
            $query = "
                    select DISTINCT(ec.pk_evento) pk_evento, e.evento, e.imagen, c.ciudad, e2.estado, date_format(e.inicio_fecha, '%d/%m/%Y') inicio_fecha_natural,
                        format(sum(efb.precio), 2) total_venta_natural, format(sum(0), 2) total_venta,
                        format(sum(0), 2) total_utilidad
                    from eventos_compras ec
                    inner join eventos e on e.pk_evento = ec.pk_evento
                    inner join escenarios es On es.pk_escenario = e.pk_escenario
                    inner join ciudades c on c.pk_ciudad = es.pk_ciudad
                    inner join estados e2  on e2.pk_estado = c.pk_estado
                    inner join usuarios_eventos ue on ue.pk_evento = e.pk_evento
                    inner join eventos_funciones_boletos efb on efb.pk_evento_compra = ec.pk_evento_compra
                    inner join eventos_funciones ef on ef.pk_evento_funcion = ec.pk_evento_funcion
                    where e.inicio_fecha >= NOW() and e.pk_evento_tipo = 1 and ue.pk_usuario = $usuario and ef.cerrado = 0
                    GROUP by ec.pk_evento, e.evento, e.imagen, c.ciudad, e2.estado,  e.inicio_fecha
                    order by e.inicio_fecha DESC
                    limit 10
            ";
        }

        Log::alert('SQL: '.$query);
        $results = DB::select($query);
        return $results;
    }

    public static function ResumenVentaEventoIndexBoxOffice($evento) {
        $query = (string)"
                select DISTINCT(ec.pk_evento) pk_evento, e.evento, e.imagen, c.ciudad, e2.estado,
                               date_format(e.inicio_fecha, '%d/%m/%Y') inicio_fecha_natural,
                    format(sum(efb.precio), 2) total_venta_natural, format(sum(0), 2) total_venta,
                    format(sum(0), 2) total_utilidad
                from eventos_compras ec
                inner join eventos e on e.pk_evento = ec.pk_evento
                inner join escenarios es On es.pk_escenario = e.pk_escenario
                inner join ciudades c on c.pk_ciudad = es.pk_ciudad
                inner join estados e2  on e2.pk_estado = c.pk_estado
                inner join eventos_funciones_boletos efb on efb.pk_evento_compra = ec.pk_evento_compra
                where e.pk_evento = $evento
                GROUP by ec.pk_evento, e.evento, e.imagen, c.ciudad, e2.estado,  e.inicio_fecha";

        Log::alert('SQL: '.$query);
        $results = DB::select($query);
        return $results;
    }

    public function save(array $options = array()) {
        if($this['codigo'] === null){
            $this['codigo'] = Str::uuid();
        }
        return parent::save($options);
    }

    public function create($innerTransaction = true) {
        if($innerTransaction) DB::beginTransaction();
        try {
            if( $this['pk_evento_compra'] === null) {
                $this->pk_usuario                  = Auth::user()->pk_usuario;
                $this->total_compra                = 0;
                $this->total_comision_aplicacion   = 0;
                $this->total_comision_tarjeta      = 0;
                $this->total_comision_pago_linea   = 0;
                $this->codigo   = Str::uuid();
                $this['creacion_pk_usuario']       = Auth::user()->pk_usuario;
                $this['creacion_fecha']            = date('Y-m-d H:i:s');
                $this->save();
                if($innerTransaction) DB::commit();
                return true;
            } else {
                throw new \App\Exceptions\ApplicationException('TICKETS_CREATE_04');
            }
        } catch(\App\Exceptions\ApplicationException $exception) {
            if($innerTransaction) DB::rollBack();
            throw $exception;
        }
    }

    public function create_openpay($innerTransaction = true) {
        if($innerTransaction) DB::beginTransaction();
        try {
            if( $this['pk_evento_compra'] === null) {
                $this->total_compra                = 0;
                $this->total_comision_aplicacion   = 0;
                $this->total_comision_tarjeta      = 0;
                $this->total_comision_pago_linea   = 0;
                $this['creacion_fecha']            = date('Y-m-d H:i:s');
                $this->save();
                if($innerTransaction) DB::commit();
                return true;
            } else {
                throw new \App\Exceptions\ApplicationException('TICKETS_CREATE_04');
            }
        } catch(\App\Exceptions\ApplicationException $exception) {
            if($innerTransaction) DB::rollBack();
            throw $exception;
        }
    }

    public function update(array $attributes = array(), array $options = array()) {
        try {
            if( $this['pk_evento_compra'] != null) {
                $objBoletos = EventosFuncionesBoletos::boletosTotales($attributes['pk_evento_compra']);
                $objEvento = Eventos::viewFind($attributes['pk_evento']);
                $objLocalidad = EventosLocalidades::viewFind($attributes['pk_evento_localidad']);
                $promocion = (int)$attributes['promocion'];

                $this->pk_evento = (int)$attributes['pk_evento'];
                $this->pk_evento_funcion = (int)$attributes['pk_evento_funcion'];
                $this->pk_evento_compra_tipo = (int)$attributes['pk_evento_compra_tipo'];

                if(isset($attributes['comentario'])){
                    $this->comentario = $attributes['comentario'];
                }
                if(isset($attributes['total_defender'])){
                    $this->total_defender = (double)$attributes['total_defender'];
                }

                $total_boleto = 0;
                $total_comision_aplicacion = 0;

                for($i = 0; $i < sizeof($objBoletos); $i++) {
                    $total_boleto = $total_boleto + (double)$objBoletos[$i]->total_boleto;
                    $total_comision_app = 0;

                    if(Session::get('TICKET_MSI')){
                        Log::info("Entro para MSI Comision App: ".Session::get('TICKET_MSI'));
                        switch(Session::get('TICKET_MSI')) {
                            case 3:
                                $comision1   = (double)\Session::get('TICKET_NEW')['step2']['conteoBoleto'] * ((double)$objLocalidad->comisionWeb / 100);
                                Log::info("Comision Web: ".$comision1);
                                $calc1 = (((double)\Session::get('TICKET_NEW')['step2']['totalBoletos'] + $comision1) * (9 / 100));
                                Log::info("Comision: ".$calc1);
                                $total_comision_app = $calc1 / (double)\Session::get('TICKET_NEW')['step2']['conteoBoleto'];
                                break;
                            case 6:
                                $comision1   = (double)\Session::get('TICKET_NEW')['step2']['conteoBoleto'] * ((double)$objLocalidad->comisionWeb / 100);
                                Log::info("Comision Web: ".$comision1);
                                $calc1 = (((double)\Session::get('TICKET_NEW')['step2']['totalBoletos'] + $comision1) * (12 / 100));
                                Log::info("Comision 1: ".$calc1);
                                $total_comision_app = $calc1 / (double)\Session::get('TICKET_NEW')['step2']['conteoBoleto'];
                                break;
                        }
                    } else {
                        switch($objEvento->pk_evento_comision_tipo) {
                            case 1:
                                if ($this->pk_evento_compra_tipo == 3) {
                                    $total_comision_app = (double)$objBoletos[$i]->total_boleto * ((double)$objLocalidad->comisionWeb / 100);
                                } else {
                                    $total_comision_app = (double)$objBoletos[$i]->total_boleto * ((double)$objLocalidad->comision / 100);
                                }
                                break;
                            case 2:
                                if ($this->pk_evento_compra_tipo == 3) {
                                    $total_comision_app = (int)$attributes['cantidad_boletos'] * (double)$objLocalidad->comisionWeb;
                                } else {
                                    $total_comision_app = (int)$attributes['cantidad_boletos'] * (double)$objLocalidad->comision;
                                }
                                break;
                            case 3:
                                if ($this->pk_evento_compra_tipo == 3) {
                                    $total_comision_app = (double)$objBoletos[$i]->total_boleto * ((double)$objLocalidad->comisionWeb / 100);
                                } else {
                                    $total_comision_app = (double)$objBoletos[$i]->total_boleto * ((double)$objLocalidad->comision / 100);
                                }
                                break;
                            case 4:
                                if ($this->pk_evento_compra_tipo == 3) {
                                    $total_comision_app = (int)$attributes['cantidad_boletos'] * (double)$objLocalidad->comisionWeb;
                                } else {
                                    if ($promocion == 1) {
                                        $total_comision_app = (int)$attributes['comision_local'];
                                    } else {
                                        $total_comision_app = (int)$attributes['cantidad_boletos'] * (double)$objLocalidad->comision;
                                    }
                                }
                                break;
                        }
                    }
                    $total_comision_aplicacion = $total_comision_aplicacion + $total_comision_app;
                }
                $this->total_compra = $total_boleto;
                $this->total_comision_aplicacion = $total_comision_aplicacion;

                $objComprasTipos = EventosComprasTipos::where('pk_evento_compra_tipo', $attributes['pk_evento_compra_tipo'])->first();
                switch((int)$objComprasTipos->pk_evento_compra_tipo) {
                    case 1:
                        $this->total_comision_tarjeta = 0;
                        break;
                    case 2:
                        if ($this->pk_evento == 331) {
                            $this->total_comision_tarjeta=0;
                        } else {
                            $this->total_comision_tarjeta = ((double)$total_boleto + ((int)$attributes['cantidad_boletos'] * (double)$objLocalidad->comision)) * ((double)$objComprasTipos->comision_porcentaje / 100);
                        }
                        break;
                    case 3:
                        $this->total_comision_tarjeta = 0;
                        if(\Session::get('TICKET_TYPE') != 2) {
                            $this->tarjeta_numero = (string)$attributes['tarjeta_numero'];
                            $this->tarjeta_propietario = (string)$attributes['tarjeta_propietario'];
                            $this->tarjeta_mes_expiracion = (string)$attributes['tarjeta_mes_expiracion'];
                            $this->tarjeta_ano_expiracion = (string)$attributes['tarjeta_ano_expiracion'];
                            $this->tarjeta_banco = (string)$attributes['tarjeta_banco'];
                        }
                        if ($this->pk_evento == 331) {} else {
                            if(Session::get('TICKET_MSI')){
                                Log::info("Entro para MSI Pago en linea: ".Session::get('TICKET_MSI'));
                                switch(Session::get('TICKET_MSI')) {
                                    case 3:
                                        $this->total_comision_pago_linea = ((((double)$total_boleto +  $this->total_comision_aplicacion) * (9 / 100)) + 3);
                                        break;
                                    case 6:
                                        $this->total_comision_pago_linea = ((((double)$total_boleto +  $this->total_comision_aplicacion) * (9 / 100)) + 3);
                                        break;
                                }
                            } else {
                                $this->total_comision_pago_linea = (((double)$total_boleto +  $this->total_comision_aplicacion) * ((double)$objComprasTipos->comision_porcentaje / 100)) + 2.5;
                            }
                        }
                        break;
                    case 4:
                        $this->total_comision_aplicacion = 0;
                        $this->total_comision_tarjeta = 0;
                        $this->total_comision_pago_linea = 0;
                        break;
                    case 5:
                        $this->total_comision_aplicacion = 0;
                        $this->total_comision_tarjeta = 0;
                        $this->total_comision_pago_linea = 0;
                        break;
                }

                $this->total_utilidad = (double)$this->total_compra - (double)$this->total_comision_aplicacion - (double)$this->total_comision_tarjeta - (double)$this->total_comision_pago_linea;
                $this->save();
            } else {
                throw new \App\Exceptions\ApplicationException('TICKETS_CREATE_04');
            }
        } catch(\App\Exceptions\ApplicationException $exception) {
            throw $exception;
        }
    }

    public function update_openpay($dataCompra, $msi, $type, array $attributes = array(), array $options = array()) {
        try {
            if( $this['pk_evento_compra'] != null) {
                $objBoletos = EventosFuncionesBoletos::boletosTotales($attributes['pk_evento_compra']);
                $objEvento = Eventos::viewFind($attributes['pk_evento']);
                $objLocalidad = EventosLocalidades::viewFind($attributes['pk_evento_localidad']);
                $promocion = (int)$attributes['promocion'];

                $this->pk_evento = (int)$attributes['pk_evento'];
                $this->pk_evento_funcion = (int)$attributes['pk_evento_funcion'];
                $this->pk_evento_compra_tipo = (int)$attributes['pk_evento_compra_tipo'];

                $total_boleto = 0;
                $total_comision_aplicacion = 0;

                for($i = 0; $i < sizeof($objBoletos); $i++) {
                    $total_boleto = $total_boleto + (double)$objBoletos[$i]->total_boleto;
                    $total_comision_app = 0;

                    if($msi >= 1){
                        Log::info("Entro para MSI Comision App: ".$msi);
                        switch($msi) {
                            case 3:
                                $comision1   = (double)$dataCompra->step2->conteoBoleto * ((double)$objLocalidad->comisionWeb / 100);
                                $calc1 = (((double)$dataCompra->step2->totalBoletos + $comision1) * (9 / 100));
                                $total_comision_app = $calc1 / (double)$dataCompra->step2->conteoBoleto;
                                break;
                            case 6:
                                $comision1   = (double)$dataCompra->step2->conteoBoleto * ((double)$objLocalidad->comisionWeb / 100);
                                $calc1 = (((double)$dataCompra->step2->totalBoletos + $comision1) * (12 / 100));
                                $total_comision_app = $calc1 / (double)$dataCompra->step2->conteoBoleto;
                                break;
                        }
                    } else {
                        switch($objEvento->pk_evento_comision_tipo) {
                            case 1:
                                if ($this->pk_evento_compra_tipo == 3) {
                                    $total_comision_app = (double)$objBoletos[$i]->total_boleto * ((double)$objLocalidad->comisionWeb / 100);
                                } else {
                                    $total_comision_app = (double)$objBoletos[$i]->total_boleto * ((double)$objLocalidad->comision / 100);
                                }
                                break;
                            case 2:
                                if ($this->pk_evento_compra_tipo == 3) {
                                    $total_comision_app = (int)$attributes['cantidad_boletos'] * (double)$objLocalidad->comisionWeb;
                                } else {
                                    $total_comision_app = (int)$attributes['cantidad_boletos'] * (double)$objLocalidad->comision;
                                }
                                break;
                            case 3:
                                if ($this->pk_evento_compra_tipo == 3) {
                                    $total_comision_app = (double)$objBoletos[$i]->total_boleto * ((double)$objLocalidad->comisionWeb / 100);
                                } else {
                                    $total_comision_app = (double)$objBoletos[$i]->total_boleto * ((double)$objLocalidad->comision / 100);
                                }
                                break;
                            case 4:
                                if ($this->pk_evento_compra_tipo == 3) {
                                    $total_comision_app = (int)$attributes['cantidad_boletos'] * (double)$objLocalidad->comisionWeb;
                                } else {
                                    if ($promocion == 1) {
                                        $total_comision_app = (int)$attributes['comision_local'];
                                    } else {
                                        $total_comision_app = (int)$attributes['cantidad_boletos'] * (double)$objLocalidad->comision;
                                    }
                                }
                                break;
                        }
                    }
                    $total_comision_aplicacion = $total_comision_aplicacion + $total_comision_app;
                }
                $this->total_compra = $total_boleto;
                $this->total_comision_aplicacion = $total_comision_aplicacion;

                $objComprasTipos = EventosComprasTipos::where('pk_evento_compra_tipo', $attributes['pk_evento_compra_tipo'])->first();
                switch((int)$objComprasTipos->pk_evento_compra_tipo) {
                    case 1:
                        $this->total_comision_tarjeta = 0;
                        break;
                    case 2:
                        if ($this->pk_evento == 331) {
                            $this->total_comision_tarjeta=0;
                        } else {
                            $this->total_comision_tarjeta = ((double)$total_boleto + ((int)$attributes['cantidad_boletos'] * (double)$objLocalidad->comision)) * ((double)$objComprasTipos->comision_porcentaje / 100);
                        }
                        break;
                    case 3:
                        $this->total_comision_tarjeta = 0;
                        if($type != 2) {
                            $this->tarjeta_numero = (string)$attributes['tarjeta_numero'];
                            $this->tarjeta_propietario = (string)$attributes['tarjeta_propietario'];
                            $this->tarjeta_mes_expiracion = (string)$attributes['tarjeta_mes_expiracion'];
                            $this->tarjeta_ano_expiracion = (string)$attributes['tarjeta_ano_expiracion'];
                            $this->tarjeta_banco = (string)$attributes['tarjeta_banco'];
                        }
                        if ($this->pk_evento == 331) {} else {
                            if($msi >= 1){
                                Log::info("Entro para MSI Pago en linea: ".$msi);
                                switch($msi) {
                                    case 3:
                                        $this->total_comision_pago_linea = ((((double)$total_boleto +  $this->total_comision_aplicacion) * (9 / 100)) + 3);
                                        break;
                                    case 6:
                                        $this->total_comision_pago_linea = ((((double)$total_boleto +  $this->total_comision_aplicacion) * (9 / 100)) + 3);
                                        break;
                                }
                            } else {
                                $this->total_comision_pago_linea = (((double)$total_boleto +  $this->total_comision_aplicacion) * ((double)$objComprasTipos->comision_porcentaje / 100)) + 2.5;
                            }
                        }
                        break;
                    case 4:
                        $this->total_comision_aplicacion = 0;
                        $this->total_comision_tarjeta = 0;
                        $this->total_comision_pago_linea = 0;
                        break;
                    case 5:
                        $this->total_comision_aplicacion = 0;
                        $this->total_comision_tarjeta = 0;
                        $this->total_comision_pago_linea = 0;
                        break;
                }

                $this->total_utilidad = (double)$this->total_compra - (double)$this->total_comision_aplicacion - (double)$this->total_comision_tarjeta - (double)$this->total_comision_pago_linea;
                $this->save();
            } else {
                throw new \App\Exceptions\ApplicationException('TICKETS_CREATE_04');
            }
        } catch(\App\Exceptions\ApplicationException $exception) {
            throw $exception;
        }
    }

    public static function totalDefenderCompra($pk_evento) {
        $query = (string)"SELECT
                SUM(eventos_compras.total_defender) AS total
            FROM
                eventos_compras
            WHERE
                eventos_compras.pk_evento = $pk_evento";

        $results = DB::select($query);
        return $results;
    }

    public static function totalWhatsappCompra($pk_evento) {
        $query = (string)"SELECT
                SUM(eventos_compras.total_defender) AS total
            FROM
                eventos_compras
            WHERE
                eventos_compras.pk_evento = $pk_evento";

        $results = DB::select($query);
        return $results;
    }

    public static function EventoIndexBoxOffice($usuario=0) {
        $query = (string)"
                select e.pk_evento, e.evento, e.imagen, c.ciudad, e2.estado, date_format(e.inicio_fecha, '%d/%m/%Y') inicio_fecha_natural,
                       es.escenario
                from eventos e
                inner join escenarios es On es.pk_escenario = e.pk_escenario
                inner join ciudades c on c.pk_ciudad = es.pk_ciudad
                inner join estados e2  on e2.pk_estado = c.pk_estado
                inner join usuarios_eventos ue on ue.pk_evento = e.pk_evento
                where e.pk_evento_status in (1, 2) and e.pk_evento_tipo = 1
                order by e.inicio_fecha DESC
                limit 5";
        if($usuario != 0){
            $query = "
                    select e.pk_evento, e.evento, e.imagen, c.ciudad, e2.estado, date_format(e.inicio_fecha, '%d/%m/%Y') inicio_fecha_natural,
                       es.escenario
                    from eventos e
                    inner join escenarios es On es.pk_escenario = e.pk_escenario
                    inner join ciudades c on c.pk_ciudad = es.pk_ciudad
                    inner join estados e2  on e2.pk_estado = c.pk_estado
                    inner join usuarios_eventos ue on ue.pk_evento = e.pk_evento
                    where e.pk_evento_status in (1, 2) and e.pk_evento_tipo = 1 and ue.pk_usuario = $usuario
                    order by e.inicio_fecha DESC
                    limit 10
            ";
        }

        Log::alert('SQL: '.$query);
        $results = DB::select($query);
        return $results;
    }

    public static function ResumenPagos($evento) {
        $query = (string)"select format(COALESCE(SUM(monto), 0), 2) pagado from gastos g where g.id_evento = ".$evento;
        Log::alert('SQL: '.$query);
        $results = DB::select($query);
        return $results;
    }

    public static function PagosEventoDetalle($evento) {
        $query = (string)"
        select g.descripcion, format(g.monto, 2) monto_text, g.monto monto,
                CASE g.motivo
                    WHEN 1 THEN 'Pago a organizador transferencia'
                    WHEN 2 THEN 'Pago a organizador efectivo'
                    WHEN 3 THEN 'Orden de pago'
                    ELSE 'Pago en boletos'
                END motivo,
                 date_format(g.fecha, '%d/%m/%Y') fecha_pago,
                 g.comentarios
        from gastos g
        where g.id_evento = $evento";

        Log::alert('SQL: '.$query);
        $results = DB::select($query);
        return $results;
    }
}
