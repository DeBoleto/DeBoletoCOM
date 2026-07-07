<?php

namespace App\Modules\Events\Models;

use Illuminate\Database\Eloquent\Model;
use App\Library\FormatValidation;
use App\Library\Errors;
use App\Exceptions\ApplicationException;
use \Exception;
use DB;
use Auth;

class EventosPeriodosVentas extends Model
{
    protected $table = "eventos_periodos_ventas";
    public $timestamps = false;
    protected $primaryKey = 'pk_evento_periodo_venta';

    protected $fillable = ['pk_evento', 'periodo_venta', 'creacion_pk_usuario', 'creacion_fecha', 'modificacion_pk_usuario', 'modificacion_fecha', 'eliminado'];

    /* RELATIONSHIP - INICIO */
    public function evento() {
        return $this->belongTo('App\Modules\Events\Models\Eventos', 'pk_evento', 'pk_evento');
    }

    public function creacionUsuario() {
        return $this->belongsTo('App\Modules\Users\Models\Usuarios', 'pk_usuario', 'creacion_pk_usuario');
    }

    public function modificacionUsuario() {
        return $this->belongsTo('App\Modules\Users\Models\Usuarios', 'pk_usuario', 'modificacion_pk_usuario');
    }
    /* RELATIONSHIP - FIN */


    public function save(array $options = array()) {

        $this['modificacion_pk_usuario'] = Auth::user()->pk_usuario;
        $this['modificacion_fecha'] = date('Y-m-d H:i:s');

        return parent::save($options);
    }

    public function create($innerTransaction = true) {

        if($innerTransaction) DB::beginTransaction();

        try {
            if( $this['pk_evento_periodo_venta'] === null) {
                $this['creacion_pk_usuario'] = Auth::user()->pk_usuario;
                $this['creacion_fecha'] = date('Y-m-d H:i:s');
                $this->save();
                if($innerTransaction) DB::commit();
                return true;
            } else {
                throw new ApplicationException('EVENTOS_CREATE_04');
            }
        } catch(ApplicationException $exception) {
            if($innerTransaction) DB::rollBack();
            throw $exception;
        }
    }

    static public function view() {
        $retorno = DB::table('eventos_periodos_ventas');
        return $retorno;
    }

    static public function viewFind($pk_evento_periodo_venta) {
        $retorno = DB::table('eventos_periodos_ventas');
        $retorno->where('pk_evento_periodo_venta', '=', $pk_evento_periodo_venta);
        return $retorno->get()->first();
    }

    public function update(array $attributes = array(), array $options = array()) {
        return save($options);
    }

    public function delete() {
        if( $this['eliminado'] == 0) {
            $this['modificacion_pk_usuario'] = Auth::user()->pk_usuario;
            $this['modificacion_fecha'] = date('Y-m-d H:i:s');
            $this['eliminado'] = 1;
            return save();
        } else {
            return false;
        }
    }


}
