<?php

namespace App\Modules\BoxOffice\Models;

use App\Modules\Shared\Models\LegacyModel;
use App\Modules\Users\Models\Usuarios;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Turno de caja de un cajero: fondo inicial, ventas ligadas y cierre
 * autorizado por un supervisor.
 *
 * @property int $pk_caja_apertura
 * @property int $pk_usuario
 * @property string|null $caja
 * @property int $estatus
 * @property \Illuminate\Support\Carbon $apertura_fecha
 * @property float $monto_apertura
 * @property \Illuminate\Support\Carbon|null $cierre_fecha
 * @property int|null $cierre_pk_usuario_autoriza
 * @property float|null $efectivo_contado
 * @property float|null $diferencia
 */
class CajaApertura extends LegacyModel
{
    public const STATUS_OPEN = 1;

    public const STATUS_CLOSED = 2;

    protected $table = 'cajas_aperturas';

    protected $primaryKey = 'pk_caja_apertura';

    protected $guarded = ['pk_caja_apertura', 'abierta_pk_usuario'];

    protected function casts(): array
    {
        return [
            'pk_usuario' => 'integer',
            'estatus' => 'integer',
            'apertura_fecha' => 'datetime',
            'cierre_fecha' => 'datetime',
            'monto_apertura' => 'float',
            'cierre_pk_usuario_autoriza' => 'integer',
            'total_compras' => 'integer',
            'total_boletos' => 'integer',
            'total_efectivo' => 'float',
            'total_terminal' => 'float',
            'total_transferencia' => 'float',
            'total_ventas' => 'float',
            'efectivo_esperado' => 'float',
            'efectivo_contado' => 'float',
            'diferencia' => 'float',
            'eliminado' => 'integer',
        ];
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('eliminado', 0);
    }

    public function scopeAbiertaDe(Builder $query, int $userId): Builder
    {
        return $query->activas()->where('pk_usuario', $userId)->where('estatus', self::STATUS_OPEN);
    }

    public function isOpen(): bool
    {
        return $this->estatus === self::STATUS_OPEN;
    }

    public function cajero(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'pk_usuario', 'pk_usuario');
    }

    public function autoriza(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'cierre_pk_usuario_autoriza', 'pk_usuario');
    }

    public function compras(): HasMany
    {
        return $this->hasMany(CajaAperturaCompra::class, 'pk_caja_apertura', 'pk_caja_apertura');
    }
}
