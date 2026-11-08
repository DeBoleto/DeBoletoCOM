<?php

namespace App\Modules\Events\Models;

use App\Modules\Shared\Models\LegacyModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Variante de precio dentro de una localidad (p. ej. "Niño", "INAPAM").
 *
 * @property int $pk_evento_localidad_especiales
 * @property int $pk_evento_localidad
 * @property string $evento_localidad
 * @property float $precio
 * @property float $comision
 * @property float $comisionWeb
 */
class EventosLocalidadesEspeciales extends LegacyModel
{
    protected $table = 'eventos_localidades_especiales';

    protected $primaryKey = 'pk_evento_localidad_especiales';

    protected function casts(): array
    {
        return [
            'pk_evento_localidad' => 'integer',
            'precio' => 'float',
            'comision' => 'float',
            'comisionWeb' => 'float',
            'eliminado' => 'boolean',
        ];
    }

    public function localidad(): BelongsTo
    {
        return $this->belongsTo(EventosLocalidades::class, 'pk_evento_localidad', 'pk_evento_localidad');
    }
}
