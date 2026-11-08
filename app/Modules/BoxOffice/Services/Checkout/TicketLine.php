<?php

namespace App\Modules\BoxOffice\Services\Checkout;

/**
 * Un boleto dentro del carrito de taquilla, ya con precio y comisiones.
 */
final class TicketLine
{
    public function __construct(
        public readonly int $localityId,
        public readonly string $localityName,
        public readonly string $seat,
        public readonly float $price,
        public readonly float $serviceFee = 0.0,
        public readonly float $paymentFee = 0.0,
        public readonly bool $courtesy = false,
        public readonly ?int $specialLocalityId = null,
        public readonly ?string $specialLocalityName = null,
    ) {}

    public static function courtesy(int $localityId, string $localityName, string $seat, ?int $specialId = null, ?string $specialName = null): self
    {
        return new self($localityId, $localityName, $seat, 0.0, 0.0, 0.0, true, $specialId, $specialName);
    }

    public function withSeat(string $seat, float $price, float $serviceFee, float $paymentFee): self
    {
        return new self($this->localityId, $this->localityName, $seat, $price, $serviceFee, $paymentFee, $this->courtesy);
    }

    public function isGeneralAdmission(): bool
    {
        return str_starts_with($this->seat, 'NA_NA');
    }

    public function isTableSeat(): bool
    {
        return str_contains($this->seat, '-');
    }

    public function chargesFees(): bool
    {
        return ! $this->courtesy && $this->price > 0;
    }

    public function toArray(): array
    {
        return [
            'locality_id' => $this->localityId,
            'locality_name' => $this->localityName,
            'seat' => $this->seat,
            'price' => $this->price,
            'service_fee' => $this->serviceFee,
            'payment_fee' => $this->paymentFee,
            'courtesy' => $this->courtesy,
            'special_locality_id' => $this->specialLocalityId,
            'special_locality_name' => $this->specialLocalityName,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (int) $data['locality_id'],
            (string) $data['locality_name'],
            (string) $data['seat'],
            (float) $data['price'],
            (float) $data['service_fee'],
            (float) $data['payment_fee'],
            (bool) $data['courtesy'],
            isset($data['special_locality_id']) ? (int) $data['special_locality_id'] : null,
            $data['special_locality_name'] ?? null,
        );
    }
}
