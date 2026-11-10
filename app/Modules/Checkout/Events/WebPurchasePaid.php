<?php

namespace App\Modules\Checkout\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Una compra web quedó pagada y sus boletos emitidos.
 */
class WebPurchasePaid
{
    use Dispatchable;

    public function __construct(
        public readonly int $purchaseId,
        public readonly ?string $whatsappPhone = null,
    ) {}
}
