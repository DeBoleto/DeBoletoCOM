<?php

namespace App\Modules\Events\Models;

use App\Modules\Shared\Models\LegacyModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $pk_evento_periodo_venta
 * @property int $pk_evento
 * @property string $periodo_venta
 */
class EventosPeriodosVentas extends LegacyModel
{
    protected $table = 'eventos_periodos_ventas';

    protected $primaryKey = 'pk_evento_periodo_venta';

    protected function casts(): array
    {
        return ['pk_evento' => 'integer', 'eliminado' => 'boolean'];
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Eventos::class, 'pk_evento', 'pk_evento');
    }
}
