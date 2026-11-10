<?php

namespace App\Modules\Payments\Data;

use Illuminate\Support\Carbon;

/**
 * Cargo devuelto por la pasarela, normalizado.
 */
final class Charge
{
    public function __construct(
        public readonly string $id,
        public readonly ChargeStatus $status,
        public readonly float $amount,
        public readonly ?string $orderId,
        public readonly string $method,
        public readonly ?string $redirectUrl = null,
        public readonly ?CardDetails $card = null,
        public readonly ?TransferDetails $transfer = null,
        public readonly ?Carbon $dueDate = null,
        public readonly ?string $errorMessage = null,
    ) {}

    public static function fromOpenPay(array $data): self
    {
        $method = (string) ($data['method'] ?? 'card');
        $paymentMethod = (array) ($data['payment_method'] ?? []);

        return new self(
            id: (string) $data['id'],
            status: ChargeStatus::fromGateway($data['status'] ?? null),
            amount: (float) ($data['amount'] ?? 0),
            orderId: isset($data['order_id']) ? (string) $data['order_id'] : null,
            method: $method,
            redirectUrl: ($paymentMethod['type'] ?? null) === 'redirect' ? ($paymentMethod['url'] ?? null) : null,
            card: isset($data['card']) && is_array($data['card']) ? CardDetails::fromOpenPay($data['card']) : null,
            transfer: $method === 'bank_account' ? TransferDetails::fromOpenPay($paymentMethod) : null,
            dueDate: isset($data['due_date']) ? Carbon::parse($data['due_date']) : null,
            errorMessage: $data['error_message'] ?? null,
        );
    }

    public function isPaid(): bool
    {
        return $this->status === ChargeStatus::Completed;
    }

    public function needsRedirect(): bool
    {
        return $this->redirectUrl !== null && ! $this->isPaid() && ! $this->status->isFinalFailure();
    }
}
