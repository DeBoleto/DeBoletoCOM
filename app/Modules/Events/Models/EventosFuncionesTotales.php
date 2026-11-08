<?php

namespace App\Modules\Events\Models;

use App\Exceptions\ApplicationException;
use App\Modules\Orders\Models\EventosComprasTipos;
use App\Modules\Tickets\Models\EventosFuncionesBoletos;
use DB;
use Illuminate\Database\Eloquent\Model;

class EventosFuncionesTotales extends Model
{
    protected $table = 'eventos_totales';

    public $timestamps = false;

    protected $primaryKey = 'pk_evento';

    protected $fillable = ['pk_evento', 'total_boletos', 'total_boletos_vendidos', 'total_venta', 'total_comision', 'total_utilidad'];

    public function evento()
    {
        return $this->belongsTo('App\Modules\Events\Models\Eventos');
    }

    public function create($innerTransaction = true)
    {

        if ($innerTransaction) {
            DB::beginTransaction();
        }

        try {
            $this->save();
            if ($innerTransaction) {
                DB::commit();
            }

            return true;
        } catch (ApplicationException $exception) {
            if ($innerTransaction) {
                DB::rollBack();
            }
            throw $exception;
        }
    }

    public function update(array $attributes = [], array $options = [])
    {

        try {
            if ($this['pk_evento_compra'] != null) {
                // OBTENEMOS LA TOTAL DE LOS BOLETOS
                $objBoletos = EventosFuncionesBoletos::boletosTotales($attributes['pk_evento_compra']);

                $this->total_compra = (float) $objBoletos[0]->total_boleto;
                $this->total_comision_aplicacion = (float) $objBoletos[0]->total_boleto * 30;

                // OBTENEMOS EL TIPO DE COMPRA
                $objComprasTipos = EventosComprasTipos::where('pk_evento_compra_tipo', $attributes['pk_evento_compra_tipo'])->first();
                switch ((int) $objComprasTipos->pk_evento_compra_tipo) {
                    case 1:
                        $this->total_comision_tarjeta = 0;
                        break;

                    case 2:
                        $this->total_comision_tarjeta = (float) $objBoletos[0]->total_boleto * ((float) $objComprasTipos->comision_porcentaje / 100);
                        break;

                    case 3:
                        // comision de tarjeta y el de openpay
                        break;
                }

                $this->total_utilidad = ((float) $this->total_compra + (float) $this->total_comision_aplicacion) - (float) $this->total_comision_pago_linea;

                $this->save();
            } else {
                throw new ApplicationException('TICKETS_CREATE_04');
            }
        } catch (ApplicationException $exception) {
            throw $exception;
        }
    }
}
