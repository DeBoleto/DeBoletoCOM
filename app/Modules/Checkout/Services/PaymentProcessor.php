<?php

namespace App\Modules\Checkout\Services;

use App\Modules\BoxOffice\Exceptions\SeatsUnavailableException;
use App\Modules\Checkout\Exceptions\CheckoutException;
use App\Modules\Checkout\Support\WebCart;
use App\Modules\Events\Models\Views\EventoVista;
use App\Modules\Orders\Enums\PurchaseStatus;
use App\Modules\Orders\Models\EventosCompras;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Data\CardChargeRequest;
use App\Modules\Payments\Data\CardDetails;
use App\Modules\Payments\Data\Charge;
use App\Modules\Payments\Data\TransferChargeRequest;
use App\Modules\Payments\Exceptions\PaymentException;
use App\Modules\Users\Models\Usuarios;
use Illuminate\Support\Str;

/**
 * Orquesta el cobro: aparta la compra, crea el cargo en la pasarela y decide
 * el siguiente paso (3D Secure, confirmación o instrucciones SPEI).
 */
class PaymentProcessor
{
    public function __construct(
        private readonly PendingPurchaseWriter $writer,
        private readonly PurchaseFinalizer $finalizer,
        private readonly PaymentGateway $gateway,
    ) {}

    /**
     * @param  array{method: 'card'|'transfer', token_id?: ?string, card_id?: ?string, cvv?: ?string, device_session_id?: ?string, save_card?: bool, whatsapp?: ?string}  $payment
     *
     * @throws PaymentException|SeatsUnavailableException|CheckoutException
     */
    public function pay(WebCart $cart, Quote $quote, EventoVista $event, Usuarios $customer, array $payment, string $owner, string $returnUrl): PaymentResult
    {
        if ($quote->total() <= 0) {
            throw new CheckoutException('El total de la compra no es válido.');
        }

        $pending = $this->writer->create($cart, $quote, $event, $customer, $payment['method'], $owner, $payment['whatsapp'] ?? null);
        $purchase = $pending->purchase;
        $description = Str::limit("Boletos {$event->evento} - deboleto.mx", 250, '');

        try {
            $customerId = $this->gateway->customerFor($customer);

            $charge = $payment['method'] === 'transfer'
                ? $this->gateway->chargeTransfer($customerId, new TransferChargeRequest(
                    $pending->amount,
                    $purchase->codigo,
                    $description,
                    now()->addHours((int) config('checkout.transfer_hours')),
                ))
                : $this->gateway->chargeCard($customerId, new CardChargeRequest(
                    $this->cardSource($customerId, $payment),
                    $pending->amount,
                    $purchase->codigo,
                    $description,
                    (string) $payment['device_session_id'],
                    $returnUrl,
                    $quote->installments,
                ));
        } catch (PaymentException $exception) {
            $this->finalizer->release($purchase);

            throw $exception;
        }

        $purchase->forceFill(['pk_openpay' => $charge->id])->save();
        $pending->log->forceFill([
            'pk_openpay' => $charge->id,
            'json_openpay' => json_encode([
                'method' => $charge->method,
                'amount' => $pending->amount,
                'order_id' => $purchase->codigo,
                'installments' => $quote->installments,
                'status' => $charge->status->value,
            ]),
        ])->save();

        return $this->resultFor($purchase, $charge);
    }

    /**
     * Estado de un cargo existente (regreso del 3D Secure, webhook o tarea programada).
     */
    public function sync(EventosCompras $purchase): Charge
    {
        $charge = $this->gateway->charge((string) $purchase->pk_openpay);

        if ($charge->isPaid()) {
            $this->finalizer->markPaid($purchase, $charge);
        } elseif ($charge->status->isFinalFailure()) {
            $this->finalizer->release($purchase);
        }

        return $charge;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function savedCards(Usuarios $customer): array
    {
        if (! $customer->pk_customer) {
            return [];
        }

        try {
            return array_map(fn (CardDetails $card) => $card->toArray(), $this->gateway->cards($customer->pk_customer));
        } catch (PaymentException) {
            return [];
        }
    }

    private function resultFor(EventosCompras $purchase, Charge $charge): PaymentResult
    {
        if ($charge->isPaid()) {
            $this->finalizer->markPaid($purchase, $charge);

            return PaymentResult::completed($purchase);
        }

        if ($charge->status->isFinalFailure()) {
            $this->finalizer->release($purchase);

            throw new PaymentException($charge->errorMessage ? 'El banco rechazó el pago.' : 'No se pudo completar el pago.');
        }

        if ($charge->needsRedirect()) {
            return PaymentResult::redirect($purchase, (string) $charge->redirectUrl);
        }

        return PaymentResult::completed($purchase);
    }

    /**
     * Tarjeta guardada (con CVV actualizado), tarjeta nueva que se guarda, o token de un solo uso.
     */
    private function cardSource(string $customerId, array $payment): string
    {
        if (! empty($payment['card_id'])) {
            $owned = collect($this->gateway->cards($customerId))->contains(fn (CardDetails $card) => $card->id === $payment['card_id']);

            if (! $owned) {
                throw new PaymentException('La tarjeta elegida no pertenece a tu cuenta.');
            }

            $this->gateway->refreshCvv($customerId, $payment['card_id'], (string) $payment['cvv']);

            return $payment['card_id'];
        }

        if (! empty($payment['save_card'])) {
            $card = $this->gateway->saveCard($customerId, (string) $payment['token_id'], (string) $payment['device_session_id']);

            if ($card->id !== null) {
                return $card->id;
            }
        }

        return (string) $payment['token_id'];
    }

    public static function statusLabel(PurchaseStatus $status): string
    {
        return match ($status) {
            PurchaseStatus::Paid => 'paid',
            PurchaseStatus::AwaitingTransfer => 'awaiting_transfer',
            PurchaseStatus::AwaitingPayment => 'processing',
            PurchaseStatus::ChargedBack => 'charged_back',
            PurchaseStatus::Rejected, PurchaseStatus::Cancelled => 'rejected',
        };
    }
}
