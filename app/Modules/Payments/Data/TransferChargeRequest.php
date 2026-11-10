<?php

namespace App\Modules\Payments\Data;

use Illuminate\Support\Carbon;

final class TransferChargeRequest
{
    public function __construct(
        public readonly float $amount,
        public readonly string $orderId,
        public readonly string $description,
        public readonly Carbon $dueDate,
    ) {}
}
