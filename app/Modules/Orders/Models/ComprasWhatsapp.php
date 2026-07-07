<?php

namespace App\Modules\Orders\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use DB;

class ComprasWhatsapp extends Model
{
    use HasFactory;

    protected $table = 'compras_whatsapp';

    protected $fillable = [
        'pk',
        'pk_evento_compra',
        'sid_twilio',
        'telefono',
        'fecha',
    ];

    public static function cantidadBoletosCompras($pk_evento, $pk_funcion, $pk_localidad, $tipo=3) {
        $query = (string)" SELECT
            count(distinct(ec.pk_evento_compra)) total_compras, count(efb.pk_evento_funcion_boleto) total_boletos
            FROM eventos_compras ec
            INNER JOIN eventos_funciones_boletos efb on efb.pk_evento_compra = ec.pk_evento_compra
            WHERE
                ec.pk_evento = $pk_evento and efb.pk_evento_localidad = $pk_localidad and
                ec.pk_evento_funcion = $pk_funcion and ec.pk_evento_compra_tipo = $tipo ";

        $results = DB::select($query);
        return $results;
    }

    public static function total_compras_whatsapp($pk_evento, $funcion){
        $query = (string)"select el.evento_localidad localidad, count(distinct(efb.pk_evento_compra)) compras, count(efb.pk_evento_funcion_boleto) boletos,
                count(distinct(efb.pk_evento_compra)) * 15 total_envio
            from eventos_funciones_boletos efb
            inner join eventos_localidades el on el.pk_evento_localidad = efb.pk_evento_localidad
            inner join eventos_compras ec on ec.pk_evento_compra = efb.pk_evento_compra
            where ec.pk_evento_compra_tipo = 3 and efb.pk_evento = $pk_evento and efb.pk_evento_funcion = $funcion
            group by el.evento_localidad";

        $results = DB::select($query);
        return $results;
    }
}
