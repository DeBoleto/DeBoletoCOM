<?php

namespace App\Modules\Events\Models;

use App\Modules\Shared\Models\LegacyModel;
use App\Modules\Tickets\Models\EventosFuncionesBoletos;
use App\Modules\Users\Models\Usuarios;
use App\Modules\Venues\Models\Escenarios;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Evento. Tabla legacy `eventos`; para lectura enriquecida usar {@see Views\EventoVista}.
 *
 * @property int $pk_evento
 * @property int $pk_escenario
 * @property int $pk_evento_status
 * @property int $pk_evento_comision_tipo
 * @property int|null $pk_evento_categoria
 * @property int|null $pk_evento_tipo
 * @property int|null $pk_boleto_formato_tipo
 * @property string $evento
 * @property string|null $imagen
 * @property int $promocion
 * @property float $promocion_porcentaje
 * @property bool $sin_estructura
 * @property int $bono
 */
class Eventos extends LegacyModel
{
    public const STATUS_DRAFT = 1;

    public const STATUS_PUBLISHED = 2;

    public const TYPE_EVENT = 1;

    public const CATEGORY_RACE = 2;

    protected $table = 'eventos';

    protected $primaryKey = 'pk_evento';

    protected function casts(): array
    {
        return [
            'pk_escenario' => 'integer',
            'pk_evento_status' => 'integer',
            'pk_evento_comision_tipo' => 'integer',
            'pk_evento_categoria' => 'integer',
            'pk_evento_tipo' => 'integer',
            'pk_boleto_formato_tipo' => 'integer',
            'promocion' => 'integer',
            'promocion_porcentaje' => 'float',
            'sin_estructura' => 'boolean',
            'bono' => 'integer',
            'eliminado' => 'boolean',
            'inicio_fecha' => 'datetime',
            'fin_fecha' => 'datetime',
        ];
    }

    public function escenario(): BelongsTo
    {
        return $this->belongsTo(Escenarios::class, 'pk_escenario', 'pk_escenario');
    }

    public function funciones(): HasMany
    {
        return $this->hasMany(EventosFunciones::class, 'pk_evento', 'pk_evento');
    }

    public function localidades(): HasMany
    {
        return $this->hasMany(EventosLocalidades::class, 'pk_evento', 'pk_evento');
    }

    public function periodoVenta(): HasOne
    {
        return $this->hasOne(EventosPeriodosVentas::class, 'pk_evento', 'pk_evento');
    }

    public function boletos(): HasMany
    {
        return $this->hasMany(EventosFuncionesBoletos::class, 'pk_evento', 'pk_evento');
    }

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(Usuarios::class, 'usuarios_eventos', 'pk_evento', 'pk_usuario')
            ->wherePivot('eliminado', 0);
    }

    public function scopePublicados(Builder $query): Builder
    {
        return $query->where('pk_evento_status', self::STATUS_PUBLISHED);
    }

    public function scopeVendibles(Builder $query): Builder
    {
        return $query->whereIn('pk_evento_status', [self::STATUS_DRAFT, self::STATUS_PUBLISHED]);
    }

    public function sellsWithoutSeatMap(): bool
    {
        return $this->pk_evento_categoria === self::CATEGORY_RACE || $this->sin_estructura;
    }
}
