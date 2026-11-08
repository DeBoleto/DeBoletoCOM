<?php

namespace App\Modules\Orders\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de formas de pago con su porcentaje de comisión bancaria.
 *
 * @property int $pk_evento_compra_tipo
 * @property string $evento_compra_tipo
 * @property float $comision_porcentaje
 */
class EventosComprasTipos extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'eventos_compras_tipos';

    protected $primaryKey = 'pk_evento_compra_tipo';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['comision_porcentaje' => 'float'];
    }
}
