<?php

namespace App\Modules\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use DB;
use Auth;
use Illuminate\Support\Facades\Log;

class EventoCodigoPromocion extends Model
{
    protected $table = "eventos_codigo_promocion";
    public $timestamps = false;
    protected $primaryKey = 'pk_evento_codigo_promocion';

    protected $fillable = ['pk_usuario', 'pk_evento', 'codigo_promocion', 'fecha_registro', 'fecha_caducidad', 'tipo', 'promocion', 'boletos'];

    public function save(array $options = array()) {
        return parent::save($options);
    }

    public function create($innerTransaction = true) {
        if($innerTransaction) DB::beginTransaction();
        try {
            if( $this['pk_evento_codigo_promocion'] === null) {
                $this['fecha_registro']            = date('Y-m-d H:i:s');
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
            if( $this['pk_evento_codigo_promocion'] != null) {
                $this->save();
            } else {
                throw new \App\Exceptions\ApplicationException('TICKETS_CREATE_10');
            }
        } catch(\App\Exceptions\ApplicationException $exception) {
            throw $exception;
        }
    }

    static public function getCodeValidate($codigo, $evento=null, $localidad=null, $boletos=null){
        if($evento != null) {
            $evento = (int)$evento;
        }
        $promocion = 0;
        $tipo = 0;
        $cantidadBoletos = null;
        $id_promocion = 0;
        $promociones = EventoCodigoPromocion::where('codigo_promocion', $codigo)->get();

        Log::info("Promo: ".print_r($promociones, true));

        for($i = 0; $i < sizeof($promociones); $i++){
            $promocion = $promociones[$i]->promocion;
            $tipo = $promociones[$i]->tipo;
            $cantidadBoletos = $promociones[$i]->boletos;
            $id_promocion = $promociones[$i]->pk_evento_codigo_promocion;
            if(isset($promociones[$i]->pk_evento)){
                if($promociones[$i]->pk_evento == $evento){
                    $promocion = $promociones[$i]->promocion;
                    $tipo = $promociones[$i]->tipo;
                    $id_promocion = $promociones[$i]->pk_evento_codigo_promocion;
                }
                else{
                    $promocion = 0;
                    $tipo = 0;
                    $id_promocion = 0;
                }
            }
            if(isset($promociones[$i]->pk_localidad)){
                if($promociones[$i]->pk_localidad == $localidad){
                    $promocion = $promociones[$i]->promocion;
                    $tipo = $promociones[$i]->tipo;
                    $id_promocion = $promociones[$i]->pk_evento_codigo_promocion;
                    break;
                }
                else{
                    $promocion = 0;
                    $tipo = 0;
                    $id_promocion = 0;
                }
            }
        }
        return [
            'id_promocion' => $id_promocion,
            'promocion' => $promocion,
            'tipo' => $tipo
        ];
    }

    static public function getCodeValidateEvento($codigo, $evento=null, $localidad=null, $boletos=null){
        if($evento != null) {
            $evento = (int)$evento;
        }
        $promocion = 0;
        $tipo = 0;
        $cantidadBoletos = null;
        $id_promocion = 0;
        $promociones = EventoCodigoPromocion::where('codigo_promocion', $codigo)->get();

        for($i = 0; $i < sizeof($promociones); $i++){
            $promocion = $promociones[$i]->promocion;
            $tipo = $promociones[$i]->tipo;
            $cantidadBoletos = $promociones[$i]->boletos;
            $id_promocion = $promociones[$i]->pk_evento_codigo_promocion;
            if(isset($promociones[$i]->pk_evento)){
                if($promociones[$i]->pk_evento == $evento){
                    $promocion = $promociones[$i]->promocion;
                    $tipo = $promociones[$i]->tipo;
                    $id_promocion = $promociones[$i]->pk_evento_codigo_promocion;
                    break;
                }
                else{
                    $promocion = 0;
                    $tipo = 0;
                    $id_promocion = 0;
                }
            }
            if(isset($promociones[$i]->pk_localidad)){
                if($promociones[$i]->pk_localidad == $localidad){
                    $promocion = $promociones[$i]->promocion;
                    $tipo = $promociones[$i]->tipo;
                    $id_promocion = $promociones[$i]->pk_evento_codigo_promocion;
                    break;
                }
                else{
                    $promocion = 0;
                    $tipo = 0;
                    $id_promocion = 0;
                }
            }
        }
        return [
            'id_promocion' => $id_promocion,
            'promocion' => $promocion,
            'tipo' => $tipo
        ];
    }
}
