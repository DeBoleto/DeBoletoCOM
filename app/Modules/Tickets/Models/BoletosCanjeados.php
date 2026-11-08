<?php

namespace App\Modules\Tickets\Models;

use App\Modules\Shared\Models\LegacyModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bitácora de cambios: boleto original reemplazado por uno nuevo.
 *
 * @property int $pk_boleto_canjeado
 * @property int $folio_boleto_anterior
 * @property int $folio_boleto_actual
 * @property string $tipo_canje
 */
class BoletosCanjeados extends LegacyModel
{
    public const UPDATED_AT = null;

    public const CREATED_BY = 'creacion_usuario';

    public const UPDATED_BY = null;

    public const TYPE_BOX_OFFICE = 'Taquilla';

    protected $table = 'boletos_canjeados';

    protected $primaryKey = 'pk_boleto_canjeado';

    protected function casts(): array
    {
        return ['folio_boleto_anterior' => 'integer', 'folio_boleto_actual' => 'integer'];
    }

    public function anterior(): BelongsTo
    {
        return $this->belongsTo(EventosFuncionesBoletos::class, 'folio_boleto_anterior', 'pk_evento_funcion_boleto');
    }

    public function actual(): BelongsTo
    {
        return $this->belongsTo(EventosFuncionesBoletos::class, 'folio_boleto_actual', 'pk_evento_funcion_boleto');
    }
}
