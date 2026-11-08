<?php

namespace App\Modules\BoxOffice\Services\Checkout;

use App\Modules\Orders\Enums\PaymentType;

/**
 * Estado del asistente de venta de un evento (equivale a TICKET_NEW de legacy).
 */
final class CheckoutState
{
    /**
     * @param  list<TicketLine>  $lines
     */
    public function __construct(
        public readonly int $eventId,
        public readonly int $functionId,
        public readonly PaymentType $paymentType,
        public readonly array $lines,
        public readonly ?int $localityId = null,
        public readonly ?string $sectionId = null,
        public readonly ?int $capacity = null,
        public readonly int $promotion = 0,
        public readonly ?int $discount = null,
        public readonly string $promotionText = '',
        public readonly ?int $purchaseId = null,
    ) {}

    public function withPurchase(int $purchaseId): self
    {
        return new self(
            $this->eventId, $this->functionId, $this->paymentType, $this->lines, $this->localityId,
            $this->sectionId, $this->capacity, $this->promotion, $this->discount, $this->promotionText, $purchaseId,
        );
    }

    public function isPurchased(): bool
    {
        return $this->purchaseId !== null;
    }

    public function subtotal(): float
    {
        return array_sum(array_map(fn (TicketLine $line) => $line->courtesy ? 0 : $line->price, $this->lines));
    }

    public function serviceFees(): float
    {
        return array_sum(array_map(fn (TicketLine $line) => $line->chargesFees() ? $line->serviceFee : 0, $this->lines));
    }

    public function paymentFees(): float
    {
        return array_sum(array_map(fn (TicketLine $line) => $line->chargesFees() ? $line->paymentFee : 0, $this->lines));
    }

    public function total(): float
    {
        return round($this->subtotal() + $this->serviceFees() + $this->paymentFees(), 2);
    }

    public function courtesyCount(): int
    {
        return count(array_filter($this->lines, fn (TicketLine $line) => $line->courtesy));
    }

    public function paidCount(): int
    {
        return count($this->lines) - $this->courtesyCount();
    }

    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'function_id' => $this->functionId,
            'payment_type' => $this->paymentType->value,
            'lines' => array_map(fn (TicketLine $line) => $line->toArray(), $this->lines),
            'locality_id' => $this->localityId,
            'section_id' => $this->sectionId,
            'capacity' => $this->capacity,
            'promotion' => $this->promotion,
            'discount' => $this->discount,
            'promotion_text' => $this->promotionText,
            'purchase_id' => $this->purchaseId,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (int) $data['event_id'],
            (int) $data['function_id'],
            PaymentType::from((int) $data['payment_type']),
            array_map(fn (array $line) => TicketLine::fromArray($line), $data['lines']),
            $data['locality_id'] ?? null,
            $data['section_id'] ?? null,
            $data['capacity'] ?? null,
            (int) ($data['promotion'] ?? 0),
            $data['discount'] ?? null,
            (string) ($data['promotion_text'] ?? ''),
            $data['purchase_id'] ?? null,
        );
    }
}
