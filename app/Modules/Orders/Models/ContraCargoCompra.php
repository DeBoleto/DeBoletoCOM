<?php

namespace App\Modules\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use DB;
use Auth;

class ContraCargoCompra extends Model
{
    protected $table = "contracargo_compra";
    public $timestamps = false;
    protected $primaryKey = 'pk_contracargo_compra';

    protected $fillable = ['pk_evento_compra', 'pk_openpay', 'estatus', 'json_respuesta', 'creacion_fecha'];

    public function save(array $options = array()) {
        return parent::save($options);
    }

    public function create($innerTransaction = true) {
        if($innerTransaction) DB::beginTransaction();
        try {
            if( $this['pk_contracargo_compra'] === null) {
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
            if( $this['pk_contracargo_compra'] != null) {
                $this->save();
            } else {
                throw new \App\Exceptions\ApplicationException('TICKETS_CREATE_10');
            }
        } catch(\App\Exceptions\ApplicationException $exception) {
            throw $exception;
        }
    }
}
