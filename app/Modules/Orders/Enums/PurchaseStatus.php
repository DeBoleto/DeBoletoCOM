<?php

namespace App\Modules\Orders\Enums;

/**
 * Catálogo eventos_compras.estatus (valores que usa deboletox).
 */
enum PurchaseStatus: int
{
    case Paid = 0;
    case ChargedBack = 1;
    case Rejected = 2;
    case Cancelled = 3;
    case AwaitingTransfer = 4;
    case AwaitingPayment = 5;

    public function isPending(): bool
    {
        return $this === self::AwaitingPayment || $this === self::AwaitingTransfer;
    }
}
