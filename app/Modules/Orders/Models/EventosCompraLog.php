<?php

namespace App\Modules\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use DB;
use Auth;

class EventosCompraLog extends Model
{
    protected $table = "eventos_compras_logs";
    public $timestamps = false;
    protected $primaryKey = 'pk_evento_compra_log';

    protected $fillable = ['pk_usuario', 'pk_openpay', 'json_compra', 'json_openpay', 'json_respuesta', 'creacion_fecha', 'msi', 'type'];

    public function creacionUsuario() {
        return $this->belongsTo('App\Modules\Users\Models\Usuarios', 'pk_usuario', 'creacion_pk_usuario');
    }

    public function save(array $options = array()) {
        return parent::save($options);
    }

    public function create($innerTransaction = true) {
        if($innerTransaction) DB::beginTransaction();
        try {
            if( $this['pk_evento_compra_log'] === null) {
                $this->pk_usuario                  = Auth::user()->pk_usuario;
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
            if( $this['pk_evento_compra_log'] != null) {
                $this->save();
            } else {
                throw new \App\Exceptions\ApplicationException('TICKETS_CREATE_10');
            }
        } catch(\App\Exceptions\ApplicationException $exception) {
            throw $exception;
        }
    }
}
