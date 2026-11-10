<?php

namespace App\Modules\Checkout\Services;

use App\Modules\Orders\Models\EventosCompraLog;
use App\Modules\Orders\Models\EventosCompras;

final class PendingPurchase
{
    public function __construct(
        public readonly EventosCompras $purchase,
        public readonly EventosCompraLog $log,
        public readonly float $amount,
    ) {}
}
