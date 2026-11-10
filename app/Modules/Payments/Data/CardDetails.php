<?php

namespace App\Modules\Payments\Data;

/**
 * Datos no sensibles de la tarjeta (número enmascarado por la pasarela).
 */
final class CardDetails
{
    public function __construct(
        public readonly ?string $id,
        public readonly string $maskedNumber,
        public readonly string $holderName,
        public readonly string $expirationMonth,
        public readonly string $expirationYear,
        public readonly ?string $bank,
        public readonly ?string $brand,
    ) {}

    public static function fromOpenPay(array $card): self
    {
        return new self(
            $card['id'] ?? null,
            (string) ($card['card_number'] ?? ''),
            (string) ($card['holder_name'] ?? ''),
            (string) ($card['expiration_month'] ?? ''),
            (string) ($card['expiration_year'] ?? ''),
            $card['bank_name'] ?? null,
            $card['brand'] ?? null,
        );
    }

    public function lastDigits(): string
    {
        return substr($this->maskedNumber, -4);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->maskedNumber,
            'last4' => $this->lastDigits(),
            'holder' => $this->holderName,
            'expiration' => $this->expirationMonth.'/'.$this->expirationYear,
            'brand' => $this->brand,
            'bank' => $this->bank,
        ];
    }
}
