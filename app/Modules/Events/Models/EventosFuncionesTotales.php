<?php

namespace App\Modules\Events\Models;

use Illuminate\Database\Eloquent\Model;
use App\Library\FormatValidation;
use App\Library\Errors;
use App\Exceptions\ApplicationException;
use \Exception;
use DB;
use Auth;

use App\Modules\Tickets\Models\EventosFuncionesBoletos;
use App\Modules\Orders\Models\EventosComprasTipos;

class EventosFuncionesTotales extends Model
{
    protected $table = "eventos_totales";
    public $timestamps = false;
    protected $primaryKey = 'pk_evento';

    protected $fillable = ['pk_evento', 'total_boletos', 'total_boletos_vendidos', 'total_venta', 'total_comision', 'total_utilidad'];

    public function evento() {
        return $this->belongsTo('App\Modules\Events\Models\Eventos');
    }

    public function create($innerTransaction = true) {

        if($innerTransaction) DB::beginTransaction();

        try {
            $this->save();
            if($innerTransaction) DB::commit();
            return true;
        } catch(ApplicationException $exception) {
            if($innerTransaction) DB::rollBack();
            throw $exception;
        }
    }

    public function update(array $attributes = array(), array $options = array()) {

        try {
            if( $this['pk_evento_compra'] != null) {
                //OBTENEMOS LA TOTAL DE LOS BOLETOS
                $objBoletos = EventosFuncionesBoletos::boletosTotales($attributes['pk_evento_compra']);

                $this->total_compra = (double)$objBoletos[0]->total_boleto;
                $this->total_comision_aplicacion   = (double)$objBoletos[0]->total_boleto * 30;

                //OBTENEMOS EL TIPO DE COMPRA
                $objComprasTipos = EventosComprasTipos::where('pk_evento_compra_tipo', $attributes['pk_evento_compra_tipo'])->first();
                switch((int)$objComprasTipos->pk_evento_compra_tipo) {
                    case 1:
                        $this->total_comision_tarjeta = 0;
                    break;

                    case 2:
                        $this->total_comision_tarjeta = (double)$objBoletos[0]->total_boleto * ((double)$objComprasTipos->comision_porcentaje / 100);
                    break;

                    case 3:
                        //comision de tarjeta y el de openpay
                    break;
                }

                $this->total_utilidad = ((double)$this->total_compra + (double)$this->total_comision_aplicacion) - (double)$this->total_comision_pago_linea;

                $this->save();
            } else {
                throw new ApplicationException('TICKETS_CREATE_04');
            }
        } catch(ApplicationException $exception) {
            throw $exception;
        }
    }

}
