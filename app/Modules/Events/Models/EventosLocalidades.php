<?php

namespace App\Modules\Events\Models;

use App\Modules\Shared\Models\LegacyModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Localidad (zona) de un evento con su precio y comisiones.
 *
 * @property int $pk_evento_localidad
 * @property int $pk_evento
 * @property string $evento_localidad
 * @property float $precio
 * @property float $comision
 * @property float $comisionWeb
 * @property int|null $capacidad
 * @property int $promocion
 * @property float $promocion_porcentaje
 */
class EventosLocalidades extends LegacyModel
{
    protected $table = 'eventos_localidades';

    protected $primaryKey = 'pk_evento_localidad';

    protected $hidden = ['reglas_text'];

    protected function casts(): array
    {
        return [
            'pk_evento' => 'integer',
            'precio' => 'float',
            'comision' => 'float',
            'comisionWeb' => 'float',
            'capacidad' => 'integer',
            'promocion' => 'integer',
            'promocion_porcentaje' => 'float',
            'eliminado' => 'boolean',
        ];
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Eventos::class, 'pk_evento', 'pk_evento');
    }

    public function especiales(): HasMany
    {
        return $this->hasMany(EventosLocalidadesEspeciales::class, 'pk_evento_localidad', 'pk_evento_localidad');
    }

    public function configuracionMesa(): HasOne
    {
        return $this->hasOne(EventoLocalidadAsiento::class, 'pk_evento_localidad', 'pk_evento_localidad');
    }

    public function promocionPrecio(): HasOne
    {
        return $this->hasOne(PromocionEventoLocalidades::class, 'pk_evento_localidad', 'pk_evento_localidad');
    }
}
