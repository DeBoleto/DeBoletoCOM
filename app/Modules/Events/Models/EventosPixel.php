<?php

namespace App\Modules\Events\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Píxel de Meta asociado a un evento para la API de conversiones.
 * Tabla legacy `eventos_pixel`.
 *
 * @property int $pk_evento
 * @property string $pixel
 * @property string $api Token de acceso de la API de conversiones.
 */
class EventosPixel extends Model
{
    public $timestamps = false;

    protected $table = 'eventos_pixel';

    protected $fillable = ['pk_evento', 'pixel', 'api'];

    protected $hidden = ['api'];
}
