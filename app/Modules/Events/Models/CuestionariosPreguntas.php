<?php

namespace App\Modules\Events\Models;

use App\Modules\Shared\Models\LegacyModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo de preguntas del registro de corredores de las carreras.
 *
 * @property int $pk_cuestionario_pregunta
 * @property string $pregunta
 * @property string $tipo
 * @property int $orden
 * @property bool $eliminado
 */
class CuestionariosPreguntas extends LegacyModel
{
    public const TYPE_TEXT = 'texto';

    public const TYPE_NUMBER = 'numero';

    public const TYPE_DATE = 'fecha';

    public const TYPE_SELECT = 'seleccion';

    protected $table = 'cuestionarios_preguntas';

    protected $primaryKey = 'pk_cuestionario_pregunta';

    protected function casts(): array
    {
        return ['orden' => 'integer', 'eliminado' => 'boolean'];
    }

    public function opciones(): HasMany
    {
        return $this->hasMany(CuestionariosPreguntasOpciones::class, 'pk_cuestionario_pregunta', 'pk_cuestionario_pregunta')
            ->where('eliminado', 0)
            ->orderBy('orden');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(EventosCuestionariosPreguntas::class, 'pk_cuestionario_pregunta', 'pk_cuestionario_pregunta');
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('eliminado', 0)->orderBy('orden')->orderBy('pregunta');
    }

    public function esSeleccion(): bool
    {
        return $this->tipo === self::TYPE_SELECT;
    }
}
