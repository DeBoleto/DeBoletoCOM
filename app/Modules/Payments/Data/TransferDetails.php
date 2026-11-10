<?php

namespace App\Modules\Payments\Data;

/**
 * Instrucciones para pagar un cargo por transferencia SPEI.
 */
final class TransferDetails
{
    public function __construct(
        public readonly string $clabe,
        public readonly string $bank,
        public readonly string $beneficiary,
    ) {}

    public static function fromOpenPay(array $paymentMethod): self
    {
        return new self(
            (string) ($paymentMethod['clabe'] ?? ''),
            (string) ($paymentMethod['bank'] ?? ''),
            (string) ($paymentMethod['name'] ?? ''),
        );
    }

    public function toArray(): array
    {
        return ['clabe' => $this->clabe, 'bank' => $this->bank, 'beneficiary' => $this->beneficiary];
    }
}
