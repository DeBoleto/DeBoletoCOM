<?php

namespace App\Modules\Orders\Models;

use App\Modules\Shared\Models\LegacyModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Bitácora de cada intento de cobro en línea. Tabla legacy `eventos_compras_logs`.
 *
 * @property int $pk_evento_compra_log
 * @property int $pk_usuario
 * @property string|null $pk_openpay
 * @property string|null $json_compra
 * @property string|null $json_openpay
 * @property string|null $json_respuesta
 */
class EventosCompraLog extends Model
{
    public const CREATED_AT = 'creacion_fecha';

    public const UPDATED_AT = null;

    protected $table = 'eventos_compras_logs';

    protected $primaryKey = 'pk_evento_compra_log';

    protected $fillable = ['pk_usuario', 'pk_openpay', 'json_compra', 'json_openpay', 'json_respuesta', 'msi', 'type', 'promo_id', 'promo_total'];

    public function freshTimestamp(): Carbon
    {
        return Carbon::now(LegacyModel::legacyTimezone());
    }
}
