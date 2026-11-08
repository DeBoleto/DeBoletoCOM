<?php

namespace App\Modules\BoxOffice\Services\Checkout;

use App\Modules\Events\Models\EventosLocalidades;
use App\Modules\Events\Models\Views\EventoVista;
use Illuminate\Validation\ValidationException;

/**
 * Valida una selección del mapa (sección, localidad y asientos o cantidad)
 * contra el XML del evento y la disponibilidad real.
 */
class SeatSelectionResolver
{
    public function __construct(
        private readonly SaleCatalog $catalog,
        private readonly SeatAvailability $availability,
    ) {}

    /**
     * @param  array{localidad: int|string, seccion: string, asientos?: array, cantidad?: int|string|null}  $input
     * @return array{locality: EventosLocalidades, section: array, seats: list<string>}
     */
    public function resolve(EventoVista $event, int $functionId, array $input): array
    {
        $localities = $this->catalog->localities($event->pk_evento);
        $section = $this->catalog->section($this->catalog->stage($event, $localities), (string) $input['seccion']);
        $locality = $localities->firstWhere('pk_evento_localidad', (int) $input['localidad']);

        if ($section === null || $locality === null || (int) $section['localityId'] !== $locality->pk_evento_localidad) {
            throw ValidationException::withMessages(['seccion' => 'La sección seleccionada no es válida.']);
        }

        $seats = $section['capacity'] !== null
            ? $this->generalAdmission($event, $functionId, $locality, $section['capacity'], (int) ($input['cantidad'] ?? 0))
            : $this->numbered($section, array_values((array) ($input['asientos'] ?? [])));

        return ['locality' => $locality, 'section' => $section, 'seats' => $seats];
    }

    /**
     * @return list<string>
     */
    private function numbered(array $section, array $seats): array
    {
        $valid = collect($section['rows'])
            ->flatMap(fn (array $row) => $row['seats'])
            ->filter(fn (array $seat) => $seat['selectable'] ?? true)
            ->pluck('id')
            ->all();

        if ($seats === [] || array_diff($seats, $valid) !== []) {
            throw ValidationException::withMessages(['asientos' => 'Selecciona asientos válidos de la sección.']);
        }

        return $seats;
    }

    /**
     * @return list<string>
     */
    private function generalAdmission(EventoVista $event, int $functionId, EventosLocalidades $locality, int $capacity, int $quantity): array
    {
        $remaining = $this->availability->remaining($event->pk_evento, $functionId, $locality->pk_evento_localidad, $capacity);

        if ($quantity < 1 || $quantity > $remaining) {
            throw ValidationException::withMessages(['cantidad' => "Solo quedan {$remaining} boletos disponibles en esta sección."]);
        }

        return array_fill(0, $quantity, 'NA_NA');
    }
}
