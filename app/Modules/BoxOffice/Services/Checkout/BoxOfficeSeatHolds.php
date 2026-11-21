<?php

namespace App\Modules\BoxOffice\Services\Checkout;

use App\Modules\BoxOffice\Exceptions\SeatsUnavailableException;
use App\Modules\Checkout\Exceptions\CheckoutException;
use App\Modules\Checkout\Services\SeatHolds;
use App\Modules\Events\Models\EventosLocalidades;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Apartados de asientos de la sesión de taquilla o administración: los mismos
 * que usa la compra web, así nadie vende un asiento que otro está cobrando.
 */
class BoxOfficeSeatHolds
{
    public function __construct(
        private readonly SeatHolds $holds,
        private readonly CheckoutStore $store,
    ) {}

    public function owner(): string
    {
        return $this->store->owner();
    }

    /**
     * @throws CheckoutException
     */
    public function hold(int $eventId, int $functionId, EventosLocalidades $locality, string $seat): void
    {
        $this->holds->holdMany($eventId, $functionId, $locality, [$seat], $this->owner(), $this->limit());
    }

    public function release(int $eventId, int $functionId, EventosLocalidades $locality, string $seat): void
    {
        $this->holds->release($eventId, $functionId, $locality, $seat, $this->owner());
    }

    public function releaseAll(): void
    {
        $this->holds->releaseAll($this->owner());
    }

    /**
     * Asientos de la localidad que otra persona tiene en proceso de compra.
     *
     * @return list<string>
     */
    public function heldByOthers(int $eventId, int $functionId, string $localityName): array
    {
        return $this->holds->heldByOthers($eventId, $functionId, $localityName, $this->owner());
    }

    /**
     * Aparta los asientos elegidos al continuar y suelta los demás de la
     * sesión. Renueva el tiempo de los que ya tenía.
     *
     * @param  list<array{locality: EventosLocalidades, section: array, seats: list<string>}>  $resolved
     *
     * @throws ValidationException
     */
    public function claim(int $eventId, int $functionId, array $resolved): void
    {
        $numbered = collect($resolved)
            ->filter(fn (array $section) => $section['section']['capacity'] === null)
            ->map(fn (array $section) => ['locality' => $section['locality'], 'seats' => $section['seats']]);

        try {
            $this->claimSeats($eventId, $functionId, $numbered);
        } catch (CheckoutException $exception) {
            throw ValidationException::withMessages(['asientos' => $exception->getMessage()]);
        }
    }

    /**
     * Confirma, dentro de la transacción de compra, que los asientos
     * numerados siguen apartados por esta sesión (o los vuelve a apartar si
     * su tiempo venció y nadie los tomó).
     *
     * @param  Collection<int, EventosLocalidades>  $localities  Por id de localidad.
     *
     * @throws SeatsUnavailableException
     */
    public function claimState(CheckoutState $state, Collection $localities): void
    {
        $numbered = collect($state->lines)
            ->reject(fn (TicketLine $line) => $line->isGeneralAdmission())
            ->groupBy(fn (TicketLine $line) => $line->localityId)
            ->map(fn (Collection $lines, int $localityId) => [
                'locality' => $localities->get($localityId),
                'seats' => $lines->pluck('seat')->unique()->values()->all(),
            ])
            ->filter(fn (array $section) => $section['locality'] !== null);

        try {
            $this->claimSeats($state->eventId, $state->functionId, $numbered);
        } catch (CheckoutException $exception) {
            throw new SeatsUnavailableException($exception->getMessage());
        }
    }

    /**
     * @param  Collection<int, array{locality: EventosLocalidades, seats: list<string>}>  $numbered
     */
    private function claimSeats(int $eventId, int $functionId, Collection $numbered): void
    {
        $owner = $this->owner();
        $keep = [];

        foreach ($numbered as $section) {
            $name = $section['locality']->evento_localidad;
            $keep[$name] = array_values(array_unique([...($keep[$name] ?? []), ...$section['seats']]));
        }

        $this->holds->releaseOtherFunctions($owner, $functionId);
        $this->holds->releaseExcept($eventId, $functionId, $owner, $keep);

        $total = array_sum(array_map('count', $keep));

        foreach ($numbered as $section) {
            $this->holds->holdMany($eventId, $functionId, $section['locality'], $section['seats'], $owner, max($total, 1));
        }
    }

    private function limit(): int
    {
        return (int) config('boxoffice.max_tickets_per_sale');
    }
}
