<?php

namespace App\Modules\Tickets\Models;

use App\Modules\Orders\Models\EventosCompras;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lugar de estacionamiento (o producto adicional) vendido con una compra.
 * Tabla legacy `eventos_estacionamiento_boleto`.
 *
 * @property int $pk_eventos_estacionamiento_boleto
 * @property int $pk_evento_compra
 * @property int $pk_evento
 * @property string $lugar
 * @property float $precio
 */
class EventoEstacionamientoBoleto extends Model
{
    public const UNASSIGNED = 'sin asignar';

    public $timestamps = false;

    protected $table = 'eventos_estacionamiento_boleto';

    protected $primaryKey = 'pk_eventos_estacionamiento_boleto';

    protected $fillable = ['pk_evento_compra', 'pk_evento', 'lugar', 'precio', 'asistencia'];

    protected function casts(): array
    {
        return [
            'pk_evento_compra' => 'integer',
            'pk_evento' => 'integer',
            'precio' => 'float',
        ];
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(EventosCompras::class, 'pk_evento_compra', 'pk_evento_compra');
    }
}
