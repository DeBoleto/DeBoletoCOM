<?php

namespace App\Modules\Tickets\Models;

use App\Modules\Events\Models\CuestionariosPreguntasOpciones;
use App\Modules\Events\Models\EventosCuestionariosPreguntas;
use App\Modules\Shared\Models\LegacyModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Respuesta de un corredor a una pregunta del registro de su carrera. Se guarda como
 * texto (fechas AAAA-MM-DD); en las de selección también se guarda la opción elegida.
 *
 * @property int $pk_evento_boleto_carrera_respuesta
 * @property int $pk_evento_boleto_carrera
 * @property int $pk_evento_cuestionario_pregunta
 * @property int|null $pk_cuestionario_pregunta_opcion
 * @property string $respuesta
 */
class EventoBoletoCarreraRespuestas extends LegacyModel
{
    public const CREATED_BY = null;

    public const UPDATED_BY = null;

    protected $table = 'evento_boleto_carrera_respuestas';

    protected $primaryKey = 'pk_evento_boleto_carrera_respuesta';

    protected function casts(): array
    {
        return [
            'pk_evento_boleto_carrera' => 'integer',
            'pk_evento_cuestionario_pregunta' => 'integer',
            'pk_cuestionario_pregunta_opcion' => 'integer',
        ];
    }

    public function corredor(): BelongsTo
    {
        return $this->belongsTo(EventoBoletoCarrera::class, 'pk_evento_boleto_carrera', 'pk_evento_boleto_carrera');
    }

    public function pregunta(): BelongsTo
    {
        return $this->belongsTo(EventosCuestionariosPreguntas::class, 'pk_evento_cuestionario_pregunta', 'pk_evento_cuestionario_pregunta');
    }

    public function opcion(): BelongsTo
    {
        return $this->belongsTo(CuestionariosPreguntasOpciones::class, 'pk_cuestionario_pregunta_opcion', 'pk_cuestionario_pregunta_opcion');
    }
}
