<?php

namespace App\Modules\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configuración de venta por mesa: cada lugar vendido se divide en N asientos.
 *
 * @property int $pk_evento_localidad_asiento
 * @property int $pk_evento
 * @property int $pk_evento_localidad
 * @property int $cantidad_asientos
 * @property bool $es_numero
 */
class EventoLocalidadAsiento extends Model
{
    public $timestamps = false;

    protected $table = 'eventos_localidades_asientos';

    protected $primaryKey = 'pk_evento_localidad_asiento';

    protected $guarded = ['pk_evento_localidad_asiento'];

    protected function casts(): array
    {
        return [
            'pk_evento' => 'integer',
            'pk_evento_localidad' => 'integer',
            'cantidad_asientos' => 'integer',
            'es_numero' => 'boolean',
        ];
    }

    public function localidad(): BelongsTo
    {
        return $this->belongsTo(EventosLocalidades::class, 'pk_evento_localidad', 'pk_evento_localidad');
    }

    /**
     * Etiquetas de los asientos de una mesa: 1..N o A..N.
     *
     * @return list<string>
     */
    public function seatLabels(): array
    {
        $labels = [];

        for ($i = 0; $i < $this->cantidad_asientos; $i++) {
            $labels[] = $this->es_numero ? (string) ($i + 1) : chr(ord('A') + $i);
        }

        return $labels;
    }
}
