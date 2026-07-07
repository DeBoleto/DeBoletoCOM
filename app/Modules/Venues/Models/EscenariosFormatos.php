<?php

namespace App\Modules\Venues\Models;

use Illuminate\Database\Eloquent\Model;
use DB;

class EscenariosFormatos extends Model
{
    protected $table = "escenarios_formatos";
    public $timestamps = false;
    protected $primaryKey = 'pk_escenario_formato';

    protected $fillable = ['escenario_formato', 'escenario_xml', 'creacion_pk_usuario', 'creacion_fecha'];

     static public function view() {
        $retorno = DB::table('view_escenarios_formatos');
        return $retorno;
    }

    static public function viewFind($pk_escenario_formato) {
        $retorno = DB::table('view_escenarios_formatos');
        $retorno->where('pk_escenario_formato', '=', $pk_escenario_formato);
        return $retorno->get()->first();
    }

    public function save(array $options = array()) {
        return parent::save($options);
    }

    public function create(array $options = array()) {
        if( $this['pk_escenario_formato'] === null) {
            $this['creacion_pk_usuario'] = 1;
            $this['creacion_fecha'] = date('Y-m-d H:i:s');

            return parent::save($options);
        } else {
            return false;
        }
    }

}
