<?php

namespace App\Modules\Orders\Models;

use App\Exceptions\ApplicationException;
use DB;
use Illuminate\Database\Eloquent\Model;

class CompraReserva extends Model
{
    protected $table = 'compra_reserva';

    public $timestamps = false;

    protected $primaryKey = 'pk_compra_reserva';

    protected $fillable = ['pk_usuario', 'pk_evento', 'pk_evento_funcion', 'pk_seccion', 'creacion_fecha'];

    public function creacionUsuario()
    {
        return $this->belongsTo('App\Modules\Users\Models\Usuarios', 'pk_usuario', 'creacion_pk_usuario');
    }

    public static function eliminarReserva($pk_usuario)
    {
        CompraReserva::where('pk_usuario', $pk_usuario)->delete();
    }

    public function save(array $options = [])
    {
        return parent::save($options);
    }

    public function create($innerTransaction = true)
    {
        if ($innerTransaction) {
            DB::beginTransaction();
        }
        try {
            if ($this['pk_evento_compra_log'] === null) {
                $this['creacion_fecha'] = date('Y-m-d H:i:s');
                $this->save();
                if ($innerTransaction) {
                    DB::commit();
                }

                return true;
            } else {
                throw new ApplicationException('TICKETS_CREATE_10');
            }
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
            if ($this['pk_evento_reserva_boleto'] != null) {
                $this->pk_evento = (int) $attributes['pk_evento'];
                $this->pk_evento_funcion = (int) $attributes['pk_evento_funcion'];
                $this->asiento = $attributes['asiento'];
                $this->save();
            } else {
                throw new ApplicationException('TICKETS_CREATE_10');
            }
        } catch (ApplicationException $exception) {
            throw $exception;
        }
    }
}
