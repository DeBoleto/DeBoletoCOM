<?php

namespace App\Modules\Orders\Models;

use App\Modules\Shared\Models\LegacyModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Contracargo recibido de la pasarela. Tabla legacy `contracargo_compra`.
 *
 * @property int $pk_contracargo_compra
 * @property int $pk_evento_compra
 * @property string $pk_openpay
 * @property int $estatus 0 abierto, 1 aceptado, 2 rechazado.
 */
class ContraCargoCompra extends Model
{
    public const STATUS_OPEN = 0;

    public const STATUS_ACCEPTED = 1;

    public const STATUS_REJECTED = 2;

    public const CREATED_AT = 'creacion_fecha';

    public const UPDATED_AT = null;

    protected $table = 'contracargo_compra';

    protected $primaryKey = 'pk_contracargo_compra';

    protected $fillable = ['pk_evento_compra', 'pk_openpay', 'estatus', 'json_respuesta'];

    protected $attributes = ['estatus' => self::STATUS_OPEN];

    public function freshTimestamp(): Carbon
    {
        return Carbon::now(LegacyModel::legacyTimezone());
    }
}
