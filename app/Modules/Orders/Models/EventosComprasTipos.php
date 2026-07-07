<?php

namespace App\Modules\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use DB;

class EventosComprasTipos extends Model
{
    protected $table = "eventos_compras_tipos";
    public $timestamps = false;
    protected $primaryKey = 'pk_evento_compra_tipo';

    protected $fillable = ['pk_evento_compra_tipo', 'evento_compra_tipo', 'comision_porcentaje'];
}
