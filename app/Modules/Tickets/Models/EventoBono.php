<?php

namespace App\Modules\Tickets\Models;

use Illuminate\Database\Eloquent\Model;
use DB;
use Auth;

class EventoBono extends Model
{
    protected $table = "eventos_bonos";
    public $timestamps = false;
    protected $primaryKey = 'pk_evento_bono';

    protected $fillable = [
        'pk_evento_compra', 'pk_evento_funcion_boleto', 'nombre', 'correo', 'telefono', 'nickname','creacion_fecha', 'modificacion_fecha'
    ];

    public function save(array $options = array()) {
        return parent::save($options);
    }

    public function create($innerTransaction = true) {
        if($innerTransaction) DB::beginTransaction();
        try {
            if( $this['pk_evento_bono'] === null) {
                $this['creacion_fecha']      = date('Y-m-d H:i:s');
                $this['modificacion_fecha']  = date('Y-m-d H:i:s');
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
            if( $this['pk_evento_bono'] != null) {
                $this['modificacion_fecha']  = date('Y-m-d H:i:s');
                $this->save();
            } else {
                throw new \App\Exceptions\ApplicationException('TICKETS_CREATE_10');
            }
        } catch(\App\Exceptions\ApplicationException $exception) {
            throw $exception;
        }
    }
}
