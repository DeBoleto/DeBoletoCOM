<?php

namespace App\Modules\Orders\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Bitácora de envíos de boletos por WhatsApp.
 *
 * @property string $pk_evento_compra Código (uuid) de la compra enviada.
 * @property string $sid_twilio Id del mensaje en el proveedor.
 * @property string $telefono
 * @property int $enviado
 */
class ComprasWhatsapp extends Model
{
    public $timestamps = false;

    protected $table = 'compras_whatsapp';

    protected $fillable = ['pk_evento_compra', 'sid_twilio', 'telefono', 'fecha', 'enviado'];
}
