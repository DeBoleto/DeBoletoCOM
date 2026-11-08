<?php

namespace App\Modules\BoxOffice\Services\Checkout;

use App\Modules\Events\Models\EventosLocalidades;
use App\Modules\Tickets\Models\EventosFuncionesBoletos;

/**
 * Verifica que los boletos del carrito sigan libres. Debe ejecutarse dentro
 * de la transacción de compra, después de bloquear las localidades.
 */
class SeatAvailability
{
    public function isAvailable(CheckoutState $state): bool
    {
        if ($state->lines === []) {
            return false;
        }

        $byLocality = collect($state->lines)->groupBy(fn (TicketLine $line) => $line->localityId);

        foreach ($byLocality as $localityId => $lines) {
            $numbered = $lines->reject(fn (TicketLine $line) => $line->isGeneralAdmission());

            if ($numbered->isNotEmpty() && $this->anySeatTaken($state, (int) $localityId, $numbered->pluck('seat')->all())) {
                return false;
            }

            $capacity = $this->capacityFor($state, (int) $localityId);

            if ($capacity !== null && $numbered->isEmpty()) {
                $issued = $this->issuedCount($state, (int) $localityId);

                if ($issued + $lines->count() > $capacity) {
                    return false;
                }
            }
        }

        return true;
    }

    public function remaining(int $eventId, int $functionId, int $localityId, int $capacity): int
    {
        $issued = EventosFuncionesBoletos::query()
            ->where('pk_evento', $eventId)
            ->deFuncionYLocalidad($functionId, $localityId)
            ->count();

        return max(0, $capacity - $issued);
    }

    /**
     * Cupo aplicable: el de la sección del mapa (cupo general) o, en venta sin
     * mapa, el de la localidad. Asientos numerados no tienen cupo.
     */
    private function capacityFor(CheckoutState $state, int $localityId): ?int
    {
        if ($state->localityId !== null) {
            return $state->capacity;
        }

        return (int) EventosLocalidades::query()->whereKey($localityId)->value('capacidad');
    }

    private function issuedCount(CheckoutState $state, int $localityId): int
    {
        return EventosFuncionesBoletos::query()
            ->where('pk_evento', $state->eventId)
            ->deFuncionYLocalidad($state->functionId, $localityId)
            ->count();
    }

    /**
     * @param  list<string>  $seats
     */
    private function anySeatTaken(CheckoutState $state, int $localityId, array $seats): bool
    {
        return EventosFuncionesBoletos::query()
            ->where('pk_evento', $state->eventId)
            ->deFuncionYLocalidad($state->functionId, $localityId)
            ->whereIn('asiento', $seats)
            ->exists();
    }
}
