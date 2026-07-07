<?php

namespace App\Modules\Events\Models;

use Illuminate\Database\Eloquent\Model;
use DB;

class EventoLocalidadAsiento extends Model
{
    protected $table = "eventos_localidades_asientos";

    protected $primaryKey = 'pk_evento_localidad_asiento';

    protected $fillable = [
        'pk_evento_localidad_asiento',
        'pk_evento',
        'pk_evento_localidad',
        'cantidad_asientos',
        'es_numero',
        'separador',
    ];

    static public function viewFind($pk_evento_localidad) {
        $retorno = DB::table('eventos_localidades_asientos');
        $retorno->where('pk_evento_localidad', '=', $pk_evento_localidad);
        return $retorno->get()->first();
    }
}
