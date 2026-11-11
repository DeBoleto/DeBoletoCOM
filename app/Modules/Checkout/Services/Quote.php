<?php

namespace App\Modules\Checkout\Services;

use App\Modules\BoxOffice\Services\Checkout\TicketLine;

/**
 * Desglose del cobro de una compra web. Es la única fuente del monto que se
 * envía a la pasarela.
 */
final class Quote
{
    /**
     * @param  list<TicketLine>  $lines
     * @param  array{label: string, price: float, service: float, payment: float}|null  $parking
     */
    public function __construct(
        public readonly array $lines,
        public readonly float $subtotal,
        public readonly float $serviceFee,
        public readonly float $paymentFee,
        public readonly ?array $parking,
        public readonly float $deliveryFee,
        public readonly float $defender,
        public readonly float $discount,
        public readonly ?Coupon $coupon,
        public readonly ?int $installments,
    ) {}

    public function parkingTotal(): float
    {
        return $this->parking === null ? 0.0 : $this->parking['price'] + $this->parking['service'] + $this->parking['payment'];
    }

    public function total(): float
    {
        return round(
            $this->subtotal + $this->serviceFee + $this->paymentFee + $this->parkingTotal()
            + $this->deliveryFee + $this->defender - $this->discount,
            2,
        );
    }

    public function paidCount(): int
    {
        return count(array_filter($this->lines, fn (TicketLine $line) => ! $line->courtesy));
    }

    public function courtesyCount(): int
    {
        return count($this->lines) - $this->paidCount();
    }

    public function toArray(): array
    {
        $generalCount = [];

        return [
            'lines' => array_map(function (TicketLine $line) use (&$generalCount) {
                $number = $line->isGeneralAdmission()
                    ? $generalCount[$line->localityId] = ($generalCount[$line->localityId] ?? 0) + 1
                    : null;

                return [
                    'label' => $number !== null ? 'Boleto '.$number : $line->seat,
                    'locality' => $line->localityName,
                    'type' => $line->specialLocalityName,
                    'price' => round($line->price, 2),
                    'courtesy' => $line->courtesy,
                ];
            }, $this->lines),
            'subtotal' => round($this->subtotal, 2),
            'service' => round($this->serviceFee, 2),
            'payment' => round($this->paymentFee, 2),
            'parking' => $this->parking === null ? null : [
                'label' => $this->parking['label'],
                'amount' => round($this->parkingTotal(), 2),
            ],
            'delivery' => round($this->deliveryFee, 2),
            'defender' => round($this->defender, 2),
            'discount' => round($this->discount, 2),
            'coupon' => $this->coupon?->toArray(),
            'installments' => $this->installments,
            'paid' => $this->paidCount(),
            'courtesies' => $this->courtesyCount(),
            'total' => $this->total(),
        ];
    }
}
