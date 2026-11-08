<?php

namespace App\Modules\Tickets\Models;

use App\Modules\Events\Models\Eventos;
use App\Modules\Events\Models\EventosFunciones;
use App\Modules\Events\Models\EventosLocalidades;
use App\Modules\Events\Models\EventosLocalidadesEspeciales;
use App\Modules\Orders\Models\EventosCompras;
use App\Modules\Shared\Models\LegacyModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Boleto emitido para una función. Tabla legacy `eventos_funciones_boletos`.
 *
 * @property int $pk_evento_funcion_boleto
 * @property int $pk_evento
 * @property int $pk_evento_funcion
 * @property int $pk_evento_localidad
 * @property int|null $pk_evento_localidad_especiales
 * @property int $pk_evento_periodo_venta
 * @property int $pk_evento_funcion_boleto_status
 * @property int $pk_evento_compra
 * @property string $asiento
 * @property float $precio
 * @property float $comision_deboleto
 * @property float $comision_servicio
 * @property string $codigo
 * @property bool $cortesia
 * @property bool $impresion
 * @property bool $ocultar
 */
class EventosFuncionesBoletos extends LegacyModel
{
    public const GENERAL_ADMISSION_SEAT = 'NA_NA';

    protected $table = 'eventos_funciones_boletos';

    protected $primaryKey = 'pk_evento_funcion_boleto';

    protected function casts(): array
    {
        return [
            'pk_evento' => 'integer',
            'pk_evento_funcion' => 'integer',
            'pk_evento_localidad' => 'integer',
            'pk_evento_localidad_especiales' => 'integer',
            'pk_evento_periodo_venta' => 'integer',
            'pk_evento_funcion_boleto_status' => 'integer',
            'pk_evento_compra' => 'integer',
            'adquisicion_pk_usuario' => 'integer',
            'precio' => 'float',
            'comision_deboleto' => 'float',
            'comision_servicio' => 'float',
            'cortesia' => 'boolean',
            'impresion' => 'boolean',
            'ocultar' => 'boolean',
        ];
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Eventos::class, 'pk_evento', 'pk_evento');
    }

    public function funcion(): BelongsTo
    {
        return $this->belongsTo(EventosFunciones::class, 'pk_evento_funcion', 'pk_evento_funcion');
    }

    public function localidad(): BelongsTo
    {
        return $this->belongsTo(EventosLocalidades::class, 'pk_evento_localidad', 'pk_evento_localidad');
    }

    public function localidadEspecial(): BelongsTo
    {
        return $this->belongsTo(EventosLocalidadesEspeciales::class, 'pk_evento_localidad_especiales', 'pk_evento_localidad_especiales');
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(EventosCompras::class, 'pk_evento_compra', 'pk_evento_compra');
    }

    public function scopeVisibles(Builder $query): Builder
    {
        return $query->where('ocultar', 0);
    }

    public function scopeDeFuncionYLocalidad(Builder $query, int $functionId, int $localityId): Builder
    {
        return $query->where('pk_evento_funcion', $functionId)->where('pk_evento_localidad', $localityId);
    }

    public function isGeneralAdmission(): bool
    {
        return str_starts_with($this->asiento, self::GENERAL_ADMISSION_SEAT);
    }

    public function total(): float
    {
        return $this->precio + $this->comision_deboleto;
    }
}
