<?php

namespace App\Modules\Tickets\Models;

use Illuminate\Database\Eloquent\Model;
use DB;
use Auth;


class EventoBoletoCarrera extends Model
{
    protected $table = "evento_boleto_carrera";
    public $timestamps = false;
    protected $primaryKey = 'pk_evento_boleto_carrera';

    protected $fillable = [
        'pk_evento_compra', 'pk_evento_funcion_boleto', 'nombre', 'correo', 'telefono', 'fecha_nacimiento', 'talla', 'tipo_sangre',
        'nombre_emergencia', 'telefono_emergencia', 'creacion_fecha', 'modificacion_fecha', 'tipo_extra'
    ];


    public function save(array $options = array()) {

        // $this['modificacion_pk_usuario'] = Auth::user()->pk_usuario;
        // $this['modificacion_fecha'] = date('Y-m-d H:i:s');
        return parent::save($options);
    }

    public function create($innerTransaction = true) {

        if($innerTransaction) DB::beginTransaction();

        try {
            if( $this['pk_evento_boleto_carrera'] === null) {
                $this['creacion_fecha']      = date('Y-m-d H:i:s');
                $this['modificacion_fecha']  = date('Y-m-d H:i:s');
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
            if( $this['pk_evento_boleto_carrera'] != null) {
                $this['modificacion_fecha']  = date('Y-m-d H:i:s');
                $this->save();
            } else {
                throw new ApplicationException('TICKETS_CREATE_10');
            }
        } catch(ApplicationException $exception) {
            throw $exception;
        }
    }
}
