<?php

namespace App\Modules\Events\Models;

use App\Modules\Shared\Models\LegacyModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Función (fecha/hora) de un evento.
 *
 * @property int $pk_evento_funcion
 * @property int $pk_evento
 * @property Carbon $fecha
 * @property bool $cerrado
 */
class EventosFunciones extends LegacyModel
{
    protected $table = 'eventos_funciones';

    protected $primaryKey = 'pk_evento_funcion';

    protected function casts(): array
    {
        return [
            'pk_evento' => 'integer',
            'fecha' => 'datetime',
            'cerrado' => 'boolean',
        ];
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Eventos::class, 'pk_evento', 'pk_evento');
    }
}
