<?php

namespace App\Modules\Checkout\Events;

use App\Modules\Payments\Data\TransferDetails;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Carbon;

/**
 * Una compra web quedó en espera de su transferencia SPEI.
 */
class WebTransferRequested
{
    use Dispatchable;

    public function __construct(
        public readonly int $purchaseId,
        public readonly TransferDetails $transfer,
        public readonly float $amount,
        public readonly ?Carbon $dueDate = null,
    ) {}
}
