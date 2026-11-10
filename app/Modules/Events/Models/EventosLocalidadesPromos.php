<?php

namespace App\Modules\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Descuento por volumen de una localidad: al comprar `num_boleto` boletos o
 * más, cada boleto se rebaja `promocion` pesos. Tabla `eventos_localidades_promos`.
 *
 * @property int $pk_localidad_promo
 * @property int $pk_evento_localidad
 * @property float $promocion
 * @property int $num_boleto
 */
class EventosLocalidadesPromos extends Model
{
    public $timestamps = false;

    protected $table = 'eventos_localidades_promos';

    protected $primaryKey = 'pk_localidad_promo';

    protected $fillable = ['pk_evento_localidad', 'promocion', 'num_boleto', 'pk_usuario', 'fecha_inicio', 'fecha_termino'];

    protected function casts(): array
    {
        return [
            'pk_evento_localidad' => 'integer',
            'promocion' => 'float',
            'num_boleto' => 'integer',
        ];
    }

    public function localidad(): BelongsTo
    {
        return $this->belongsTo(EventosLocalidades::class, 'pk_evento_localidad', 'pk_evento_localidad');
    }
}
