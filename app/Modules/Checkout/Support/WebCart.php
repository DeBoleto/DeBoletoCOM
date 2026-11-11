<?php

namespace App\Modules\Checkout\Support;

use App\Modules\BoxOffice\Services\Checkout\CheckoutState;
use Illuminate\Support\Carbon;

/**
 * Selección de la compra web lista para pagar: boletos con precio, producto
 * adicional (estacionamiento) y hasta cuándo se respetan los asientos.
 */
final class WebCart
{
    public function __construct(
        public readonly CheckoutState $selection,
        public readonly bool $parking,
        public readonly int $heldUntil,
    ) {}

    public function eventId(): int
    {
        return $this->selection->eventId;
    }

    public function isExpired(?Carbon $now = null): bool
    {
        return ($now ?? now())->timestamp > $this->heldUntil;
    }

    /**
     * Asientos numerados (sin cupo general) que debe respetar la retención.
     *
     * @return list<string>
     */
    public function numberedSeats(): array
    {
        return array_merge([], ...array_values($this->numberedSeatsByLocality()));
    }

    /**
     * @return array<int, list<string>> pk_evento_localidad => asientos numerados.
     */
    public function numberedSeatsByLocality(): array
    {
        $seats = [];

        foreach ($this->selection->lines as $line) {
            if (! $line->isGeneralAdmission() && ! str_ends_with($line->seat, (string) config('boxoffice.rules.extra_chair.seat_suffix'))) {
                $seats[$line->localityId][] = $line->seat;
            }
        }

        return $seats;
    }

    public function toArray(): array
    {
        return [
            'selection' => $this->selection->toArray(),
            'parking' => $this->parking,
            'held_until' => $this->heldUntil,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            CheckoutState::fromArray($data['selection']),
            (bool) ($data['parking'] ?? false),
            (int) $data['held_until'],
        );
    }
}
