<?php

namespace App\Modules\BoxOffice\Models;

use Illuminate\Database\Eloquent\Model;

class PuntosVentas extends Model
{
    protected $table = "puntos_ventas";
    public $timestamps = false;
    protected $primaryKey = 'pk_punto_venta';

    protected $fillable = ['pk_evento', 'lugar_compra', 'direccion', 'horario1', 'horario2', 'creacion_pk_usuario', 'creacion_fecha'];
}
