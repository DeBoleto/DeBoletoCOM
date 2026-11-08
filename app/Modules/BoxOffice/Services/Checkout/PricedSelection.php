<?php

namespace App\Modules\BoxOffice\Services\Checkout;

final class PricedSelection
{
    /**
     * @param  list<TicketLine>  $lines
     */
    public function __construct(
        public readonly array $lines,
        public readonly int $promotion,
        public readonly string $promotionText,
    ) {}
}
