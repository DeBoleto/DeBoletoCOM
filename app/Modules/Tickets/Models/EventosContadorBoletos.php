<?php

namespace App\Modules\Tickets\Models;

use App\Modules\Shared\Models\LegacyModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de acceso: un boleto escaneado en la entrada del evento.
 *
 * @property int $pk_evento_contador_boleto
 * @property int $pk_evento
 * @property int $pk_evento_funcion
 * @property int $pk_evento_funcion_boleto
 */
class EventosContadorBoletos extends LegacyModel
{
    public const UPDATED_AT = null;

    public const UPDATED_BY = null;

    protected $table = 'eventos_contador_boletos';

    protected $primaryKey = 'pk_evento_contador_boleto';

    protected function casts(): array
    {
        return [
            'pk_evento' => 'integer',
            'pk_evento_funcion' => 'integer',
            'pk_evento_funcion_boleto' => 'integer',
        ];
    }

    public function boleto(): BelongsTo
    {
        return $this->belongsTo(EventosFuncionesBoletos::class, 'pk_evento_funcion_boleto', 'pk_evento_funcion_boleto');
    }
}
