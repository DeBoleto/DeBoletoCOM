<?php

namespace App\Modules\Events\Models;

use App\Modules\Shared\Models\LegacyModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Opción de una pregunta de tipo selección (ej. talla de playera).
 *
 * @property int $pk_cuestionario_pregunta_opcion
 * @property int $pk_cuestionario_pregunta
 * @property string $opcion
 * @property int $orden
 * @property bool $eliminado
 */
class CuestionariosPreguntasOpciones extends LegacyModel
{
    protected $table = 'cuestionarios_preguntas_opciones';

    protected $primaryKey = 'pk_cuestionario_pregunta_opcion';

    protected function casts(): array
    {
        return [
            'pk_cuestionario_pregunta' => 'integer',
            'orden' => 'integer',
            'eliminado' => 'boolean',
        ];
    }

    public function pregunta(): BelongsTo
    {
        return $this->belongsTo(CuestionariosPreguntas::class, 'pk_cuestionario_pregunta', 'pk_cuestionario_pregunta');
    }
}
