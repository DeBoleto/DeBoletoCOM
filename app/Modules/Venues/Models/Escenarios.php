<?php

namespace App\Modules\Venues\Models;

use App\Modules\Events\Models\Eventos;
use App\Modules\Shared\Models\LegacyModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Recinto. Tabla legacy `escenarios`.
 *
 * @property int $pk_escenario
 * @property int $pk_ciudad
 * @property string $escenario
 * @property string|null $escenario_imagen
 * @property string|null $xml_escenario
 * @property string|null $direccion
 */
class Escenarios extends LegacyModel
{
    protected $table = 'escenarios';

    protected $primaryKey = 'pk_escenario';

    protected $hidden = ['xml_escenario'];

    protected function casts(): array
    {
        return [
            'pk_ciudad' => 'integer',
            'latitud' => 'float',
            'longitud' => 'float',
            'eliminado' => 'boolean',
        ];
    }

    public function ciudad(): BelongsTo
    {
        return $this->belongsTo(Ciudades::class, 'pk_ciudad', 'pk_ciudad');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(Eventos::class, 'pk_escenario', 'pk_escenario');
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('eliminado', 0);
    }
}
