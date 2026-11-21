<?php

namespace App\Modules\BoxOffice\Models;

use App\Modules\Orders\Models\EventosCompras;
use App\Modules\Shared\Models\LegacyModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Compra de taquilla registrada en una apertura de caja.
 *
 * @property int $pk_caja_apertura_compra
 * @property int $pk_caja_apertura
 * @property int $pk_evento_compra
 */
class CajaAperturaCompra extends LegacyModel
{
    public const UPDATED_AT = null;

    public const UPDATED_BY = null;

    protected $table = 'cajas_aperturas_compras';

    protected $primaryKey = 'pk_caja_apertura_compra';

    protected $guarded = ['pk_caja_apertura_compra'];

    protected function casts(): array
    {
        return [
            'pk_caja_apertura' => 'integer',
            'pk_evento_compra' => 'integer',
        ];
    }

    public function apertura(): BelongsTo
    {
        return $this->belongsTo(CajaApertura::class, 'pk_caja_apertura', 'pk_caja_apertura');
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(EventosCompras::class, 'pk_evento_compra', 'pk_evento_compra');
    }
}
