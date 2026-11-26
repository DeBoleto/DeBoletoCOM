<?php

namespace App\Modules\BoxOffice\Services\Checkout;

use Illuminate\Validation\ValidationException;

/**
 * No se puede dejar un solo asiento libre entre dos asientos elegidos de la
 * misma fila, entre uno elegido y uno apartado o vendido, ni a la orilla de la
 * fila junto a uno elegido: se piden asientos pegados. Solo aplica a la venta
 * en línea; la taquilla y el dashboard no la validan.
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

        if ($chosen === []) {
            return;
        }

        $taken = $this->catalog->seatStatuses($eventId, $functionId, $resolved['locality']->pk_evento_localidad)
            + array_flip(array_map('strval', $alsoTaken));

        foreach ($resolved['section']['rows'] as $row) {
            $byX = collect($row['seats'])->keyBy('x');
            $state = fn (?array $seat) => match (true) {
                $seat === null || ! ($seat['selectable'] ?? true) => 'edge',
                isset($chosen[(string) $seat['id']]) => 'chosen',
                isset($taken[(string) $seat['id']]) => 'taken',
                default => 'open',
            };

            foreach ($row['seats'] as $seat) {
                if ($state($seat) !== 'open') {
                    continue;
                }

                $sides = [$state($byX->get($seat['x'] - 1)), $state($byX->get($seat['x'] + 1))];

                if (! in_array('chosen', $sides, true) || in_array('open', $sides, true)) {
                    continue;
                }

                $where = "el asiento {$seat['id']} de la fila {$row['fila']}";

                if ($sides === ['chosen', 'chosen']) {
                    throw ValidationException::withMessages([
                        'asientos' => "Selecciona asientos pegados: quedó libre {$where} entre dos de los asientos elegidos.",
                    ]);
                }

                if (in_array('taken', $sides, true)) {
                    throw ValidationException::withMessages([
                        'asientos' => "Selecciona asientos pegados: quedó libre {$where} entre los asientos elegidos y uno ocupado.",
                    ]);
                }

                if (count($chosen) > 1) {
                    throw ValidationException::withMessages([
                        'asientos' => "Selecciona asientos pegados: quedó libre {$where} a la orilla, junto a los asientos elegidos.",
                    ]);
                }
            }
        }
    }
}
