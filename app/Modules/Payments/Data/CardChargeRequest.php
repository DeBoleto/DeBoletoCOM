<?php

namespace App\Modules\Payments\Data;

final class CardChargeRequest
{
    public function __construct(
        public readonly string $sourceId,
        public readonly float $amount,
        public readonly string $orderId,
        public readonly string $description,
        public readonly string $deviceSessionId,
        public readonly string $redirectUrl,
        public readonly ?int $installments = null,
    ) {}
}
