<?php

namespace App\Modules\Orders\Models;

use App\Modules\Events\Models\Eventos;
use App\Modules\Events\Models\EventosFunciones;
use App\Modules\Orders\Enums\PurchaseStatus;
use App\Modules\Shared\Models\LegacyModel;
use App\Modules\Tickets\Models\EventoEstacionamientoBoleto;
use App\Modules\Tickets\Models\EventosFuncionesBoletos;
use App\Modules\Users\Models\Usuarios;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Compra (orden) de boletos. Tabla legacy `eventos_compras`.
 *
 * @property int $pk_evento_compra
 * @property int $pk_usuario
 * @property int|null $pk_evento
 * @property int|null $pk_evento_funcion
 * @property int|null $pk_evento_compra_tipo
 * @property string $codigo
 * @property float $total_compra
 * @property float $total_comision_aplicacion
 * @property float $total_comision_tarjeta
 * @property float $total_comision_pago_linea
 * @property float $total_utilidad
 * @property int $estatus
 * @property string|null $pk_openpay
 * @property float|null $total_descuento
 * @property float|null $total_defender
 * @property float|null $total_envio
 */
class EventosCompras extends LegacyModel
{
    public const UPDATED_AT = null;

    public const UPDATED_BY = null;

    protected $table = 'eventos_compras';

    protected $primaryKey = 'pk_evento_compra';

    protected $hidden = [
        'tarjeta_numero', 'tarjeta_propietario', 'tarjeta_mes_expiracion',
        'tarjeta_ano_expiracion', 'tarjeta_banco', 'pk_openpay',
    ];

    protected function casts(): array
    {
        return [
            'pk_usuario' => 'integer',
            'pk_evento' => 'integer',
            'pk_evento_funcion' => 'integer',
            'pk_evento_compra_tipo' => 'integer',
            'total_compra' => 'float',
            'total_comision_aplicacion' => 'float',
            'total_comision_tarjeta' => 'float',
            'total_comision_pago_linea' => 'float',
            'total_utilidad' => 'float',
            'estatus' => 'integer',
            'total_descuento' => 'float',
            'total_defender' => 'float',
            'total_envio' => 'float',
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

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'pk_usuario', 'pk_usuario');
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(EventosComprasTipos::class, 'pk_evento_compra_tipo', 'pk_evento_compra_tipo');
    }

    public function boletos(): HasMany
    {
        return $this->hasMany(EventosFuncionesBoletos::class, 'pk_evento_compra', 'pk_evento_compra');
    }

    public function estacionamientos(): HasMany
    {
        return $this->hasMany(EventoEstacionamientoBoleto::class, 'pk_evento_compra', 'pk_evento_compra');
    }

    public function status(): ?PurchaseStatus
    {
        return PurchaseStatus::tryFrom((int) $this->estatus);
    }
}
