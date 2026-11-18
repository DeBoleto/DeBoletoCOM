<?php

namespace App\Modules\BoxOffice\Services\Checkout;

use Illuminate\Validation\ValidationException;

/**
 * No se puede dejar un solo asiento libre entre dos asientos elegidos de la
 * misma fila: se piden asientos pegados. Aplica a la web y a la taquilla.
 */
class SeatGapRule
{
    public function __construct(private readonly SaleCatalog $catalog) {}

    /**
     * @param  array{locality: \App\Modules\Events\Models\EventosLocalidades, section: array, seats: list<string>}  $resolved
     * @param  list<string>  $alsoTaken  Asientos ocupados que no están vendidos (apartados por otros).
     *
     * @throws ValidationException
     */
    public function ensure(int $eventId, int $functionId, array $resolved, array $alsoTaken = []): void
    {
        if ($resolved['section']['capacity'] !== null) {
            return;
        }

        $chosen = array_flip(array_map('strval', $resolved['seats']));

        if (count($chosen) < 2) {
            return;
        }

        $taken = $this->catalog->seatStatuses($eventId, $functionId, $resolved['locality']->pk_evento_localidad)
            + array_flip($alsoTaken);

        foreach ($resolved['section']['rows'] as $row) {
            $byX = collect($row['seats'])->keyBy('x');

            foreach ($row['seats'] as $seat) {
                $id = (string) $seat['id'];

                if (isset($chosen[$id]) || ! ($seat['selectable'] ?? true) || isset($taken[$id])) {
                    continue;
                }

                $left = $byX->get($seat['x'] - 1);
                $right = $byX->get($seat['x'] + 1);

                if ($left && $right && isset($chosen[(string) $left['id']], $chosen[(string) $right['id']])) {
                    throw ValidationException::withMessages([
                        'asientos' => "Selecciona asientos pegados: quedó libre el asiento {$id} de la fila {$row['fila']} entre dos de los asientos elegidos.",
                    ]);
                }
            }
        }
    }
}
