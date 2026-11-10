<?php

namespace App\Modules\Checkout\Services;

/**
 * Opciones del cliente en el paso de pago que modifican el total.
 */
final class QuoteOptions
{
    public function __construct(
        public readonly ?int $installments = null,
        public readonly bool $defender = false,
        public readonly ?string $coupon = null,
    ) {}
}
