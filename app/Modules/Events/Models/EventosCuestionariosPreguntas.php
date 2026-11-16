<?php

namespace App\Modules\Events\Models;

use App\Modules\Shared\Models\LegacyModel;
use App\Modules\Tickets\Models\EventoBoletoCarreraRespuestas;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pregunta que el organizador eligió para el registro de corredores de su carrera.
 *
 * @property int $pk_evento_cuestionario_pregunta
 * @property int $pk_evento
 * @property int $pk_cuestionario_pregunta
 * @property int $orden
 * @property bool $obligatoria
 * @property bool $eliminado
 */
class EventosCuestionariosPreguntas extends LegacyModel
{
    protected $table = 'eventos_cuestionarios_preguntas';

    protected $primaryKey = 'pk_evento_cuestionario_pregunta';

    protected function casts(): array
    {
        return [
            'pk_evento' => 'integer',
            'pk_cuestionario_pregunta' => 'integer',
            'orden' => 'integer',
            'obligatoria' => 'boolean',
            'eliminado' => 'boolean',
        ];
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Eventos::class, 'pk_evento', 'pk_evento');
    }

    public function pregunta(): BelongsTo
    {
        return $this->belongsTo(CuestionariosPreguntas::class, 'pk_cuestionario_pregunta', 'pk_cuestionario_pregunta');
    }

    public function respuestas(): HasMany
    {
        return $this->hasMany(EventoBoletoCarreraRespuestas::class, 'pk_evento_cuestionario_pregunta', 'pk_evento_cuestionario_pregunta');
    }
}
