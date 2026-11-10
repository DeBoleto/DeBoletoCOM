<?php

namespace App\Modules\Orders\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Código promocional de compra web. Tabla legacy `eventos_codigo_promocion`.
 *
 * @property int $pk_evento_codigo_promocion
 * @property int|null $pk_evento
 * @property int|null $pk_localidad
 * @property string $codigo_promocion
 * @property int $tipo
 * @property float $promocion
 * @property int|null $boletos Mínimo de boletos para aplicar el código.
 * @property Carbon|null $fecha_caducidad
 */
class EventoCodigoPromocion extends Model
{
    public const TYPE_PERCENTAGE = 1;

    public const TYPE_FIXED = 2;

    public const TYPE_FREE_TICKETS = 3;

    public const CREATED_AT = 'fecha_registro';

    public const UPDATED_AT = null;

    protected $table = 'eventos_codigo_promocion';

    protected $primaryKey = 'pk_evento_codigo_promocion';

    protected $fillable = ['pk_usuario', 'pk_evento', 'pk_localidad', 'codigo_promocion', 'fecha_caducidad', 'tipo', 'promocion', 'boletos'];

    protected function casts(): array
    {
        return [
            'pk_evento' => 'integer',
            'pk_localidad' => 'integer',
            'tipo' => 'integer',
            'promocion' => 'float',
            'boletos' => 'integer',
            'fecha_caducidad' => 'date',
        ];
    }

    public function scopeVigentes(Builder $query, Carbon $today): Builder
    {
        return $query->where(fn (Builder $query) => $query
            ->whereNull('fecha_caducidad')
            ->orWhereDate('fecha_caducidad', '>=', $today->toDateString()));
    }

    /**
     * Un código sin evento o sin localidad aplica a cualquiera.
     */
    public function appliesTo(int $eventId, ?int $localityId): bool
    {
        if ($this->pk_evento !== null && $this->pk_evento !== $eventId) {
            return false;
        }

        return $this->pk_localidad === null || $this->pk_localidad === $localityId;
    }
}
