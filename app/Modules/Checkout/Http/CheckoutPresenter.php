<?php

namespace App\Modules\Checkout\Http;

use App\Modules\BoxOffice\Support\EventImage;
use App\Modules\Events\Models\Views\EventoFuncionVista;
use App\Modules\Events\Models\Views\EventoVista;

/**
 * Datos del evento y la función que comparten las pantallas de compra.
 */
final class CheckoutPresenter
{
    public static function event(EventoVista $event): array
    {
        return [
            'id' => $event->pk_evento,
            'slug' => (string) $event->getAttribute('url'),
            'name' => $event->evento,
            'image' => EventImage::url($event->imagen),
            'venue' => $event->direccionVisible(),
            'city' => $event->ciudad,
            'state' => $event->estado,
            'isBond' => (int) $event->bono === 1,
        ];
    }

    public static function function(EventoFuncionVista $function): array
    {
        return [
            'id' => $function->pk_evento_funcion,
            'date' => $function->fecha_natural,
            'time' => $function->hora_natural,
            'iso' => $function->fecha,
        ];
    }
}
