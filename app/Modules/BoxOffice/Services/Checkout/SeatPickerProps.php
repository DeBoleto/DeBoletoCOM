<?php

namespace App\Modules\BoxOffice\Services\Checkout;

use App\Modules\BoxOffice\Support\EventImage;
use App\Modules\Events\Models\EventosLocalidades;
use App\Modules\Events\Models\Views\EventoFuncionVista;
use App\Modules\Events\Models\Views\EventoVista;

/**
 * Props comunes de las pantallas que eligen función, zona y asientos.
 */
class SeatPickerProps
{
    public function __construct(private readonly SaleCatalog $catalog) {}

    public function for(EventoVista $event): array
    {
        $localities = $this->catalog->localities($event->pk_evento);

        return [
            'event' => self::event($event),
            'functions' => $this->catalog->functions($event->pk_evento)->map(fn (EventoFuncionVista $function) => [
                'id' => $function->pk_evento_funcion,
                'date' => $function->fecha_natural,
                'time' => $function->hora_natural,
            ])->values(),
            'localities' => $localities->map(fn (EventosLocalidades $locality) => [
                'id' => $locality->pk_evento_localidad,
                'name' => $locality->evento_localidad,
                'price' => $locality->precio,
                'specials' => [],
            ])->values(),
            'stage' => $this->catalog->stage($event, $localities),
        ];
    }

    public static function event(EventoVista $event): array
    {
        return [
            'id' => $event->pk_evento,
            'name' => $event->evento,
            'image' => EventImage::url($event->imagen),
            'venue' => $event->direccionVisible(),
            'city' => $event->ciudad,
            'state' => $event->estado,
        ];
    }
}
