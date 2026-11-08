<?php

namespace App\Modules\Events\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Bloqueo temporal de asientos mientras una sesión completa la compra.
 *
 * @property int $pk_evento_reserva_boleto
 * @property string $pk_usuario Id de sesión o de usuario que reservó.
 * @property int $pk_evento
 * @property int $pk_evento_funcion
 * @property string $pk_seccion
 * @property string $asiento
 */
class EventoReservaBoleto extends Model
{
    public const CREATED_AT = 'creacion_fecha';

    public const UPDATED_AT = null;

    protected $table = 'eventos_reserva_boleto';

    protected $primaryKey = 'pk_evento_reserva_boleto';

    protected $fillable = ['pk_usuario', 'pk_evento', 'pk_evento_funcion', 'pk_seccion', 'asiento'];

    public static function releaseFor(string $owner): void
    {
        static::query()->where('pk_usuario', $owner)->delete();
    }
}
