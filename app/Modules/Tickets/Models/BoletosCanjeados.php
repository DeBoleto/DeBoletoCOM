<?php

namespace App\Modules\Tickets\Models;

use Illuminate\Database\Eloquent\Model;
use DB;
use Auth;

class BoletosCanjeados extends Model
{
    protected $table = "boletos_canjeados";
    public $timestamps = false;
    protected $primaryKey = 'pk_boleto_canjeado';

    protected $fillable = ['pk_boleto_canjeado', 'folio_boleto_anterior', 'folio_boleto_actual', 'creacion_fecha', 'creacion_usuario', 'tipo_canje'];

    /* RELATIONSHIPS - INICIO */
    public function eventosfuncionesboletos() {
        return $this->belongsTo('App\Modules\Tickets\Models\EventosFuncionesBoletos', 'pk_evento_funcion_boleto', 'folio_boleto_anterior');
    }



    /* RELATIONSHIPS - FIN */

    public function save(array $options = array()) {

         $this['creacion_fecha'] = date('Y-m-d H:i:s');
        $this['creacion_usuario'] = Auth::user()->pk_usuario;
         return parent::save($options);
    }

    public function create(array $options = array()) {
        if( $this['pk_boleto_canjeado'] === null) {
            $this['creacion_fecha'] = date('Y-m-d H:i:s');
            $this['creacion_usuario'] = Auth::user()->pk_usuario;
            return save($options);
        } else {
            return false;
        }
    }


}
