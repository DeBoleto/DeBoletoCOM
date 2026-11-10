<?php

namespace App\Modules\Checkout\Services;

use App\Modules\Orders\Models\EventosCompras;

final class PaymentResult
{
    private function __construct(
        public readonly EventosCompras $purchase,
        public readonly ?string $redirectUrl,
    ) {}

    public static function completed(EventosCompras $purchase): self
    {
        return new self($purchase, null);
    }

    /**
     * El banco debe autenticar al cliente (3D Secure) antes de cobrar.
     */
    public static function redirect(EventosCompras $purchase, string $url): self
    {
        return new self($purchase, $url);
    }

    public function needsRedirect(): bool
    {
        return $this->redirectUrl !== null;
    }
}
