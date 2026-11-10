<?php

namespace App\Modules\Checkout\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Checkout\Services\PaymentProcessor;
use App\Modules\Checkout\Services\PendingPurchaseWriter;
use App\Modules\Checkout\Services\PurchaseFinalizer;
use App\Modules\Orders\Enums\PaymentType;
use App\Modules\Orders\Enums\PurchaseStatus;
use App\Modules\Orders\Models\EventosCompras;
use App\Modules\Payments\Exceptions\PaymentException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Notificaciones de OpenPay para las compras web.
 *
 * OpenPay no firma sus notificaciones: el webhook se registra con usuario y
 * contraseña (Basic Auth) y, además, el estado de cada cargo se vuelve a
 * consultar a la API en lugar de confiar en el contenido recibido.
 */
class OpenPayWebhookController extends Controller
{
    private const PAID = ['charge.succeeded', 'order.payment.received'];

    private const FAILED = [
        'charge.failed' => PurchaseStatus::Rejected,
        'transaction.expired' => PurchaseStatus::Rejected,
        'order.expired' => PurchaseStatus::Rejected,
        'order.cancelled' => PurchaseStatus::Cancelled,
        'order.payment.cancelled' => PurchaseStatus::Cancelled,
        'charge.cancelled' => PurchaseStatus::Cancelled,
    ];

    public function __invoke(Request $request, PaymentProcessor $payments, PurchaseFinalizer $finalizer): JsonResponse
    {
        if (! $this->authorized($request)) {
            return response()->json(['message' => 'No autorizado.'], 401);
        }

        $type = (string) $request->input('type');

        if ($type === 'verification') {
            Log::info('Verificación del webhook de OpenPay', ['code' => $request->input('verification_code')]);

            return response()->json(['status' => 'ok']);
        }

        $chargeId = $request->input('transaction.id');

        if (! is_string($chargeId) || $chargeId === '') {
            return response()->json(['status' => 'ignored']);
        }

        $purchase = EventosCompras::query()
            ->where('pk_openpay', $chargeId)
            ->where('pk_evento_compra_tipo', PaymentType::Web->value)
            ->first();

        // Las compras de deboletox las atiende su propio webhook.
        if ($purchase === null || ($finalizer->snapshot($purchase)['source'] ?? null) !== PendingPurchaseWriter::SOURCE) {
            return response()->json(['status' => 'ignored']);
        }

        try {
            match (true) {
                in_array($type, self::PAID, true) => $payments->sync($purchase),
                array_key_exists($type, self::FAILED) => $this->failed($payments, $finalizer, $purchase, self::FAILED[$type]),
                $type === 'chargeback.created' => $finalizer->chargebackOpened($purchase, $chargeId, $request->all()),
                $type === 'chargeback.rejected' => $finalizer->chargebackRejected($chargeId),
                $type === 'chargeback.accepted' => $finalizer->chargebackAccepted($purchase, $chargeId),
                default => null,
            };
        } catch (PaymentException $exception) {
            Log::warning('No se pudo consultar el cargo del webhook', ['cargo' => $chargeId, 'message' => $exception->getMessage()]);

            return response()->json(['message' => 'Reintentar.'], 503);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Antes de liberar se confirma con la API que el cargo realmente no se pagó.
     */
    private function failed(PaymentProcessor $payments, PurchaseFinalizer $finalizer, EventosCompras $purchase, PurchaseStatus $status): void
    {
        $charge = $payments->sync($purchase);

        if (! $charge->isPaid()) {
            $finalizer->release($purchase, $status);
        }
    }

    private function authorized(Request $request): bool
    {
        $user = (string) config('services.openpay.webhook_user');
        $password = (string) config('services.openpay.webhook_password');

        if ($user === '' || $password === '') {
            Log::error('El webhook de OpenPay no tiene credenciales configuradas.');

            return false;
        }

        return hash_equals($user, (string) $request->getUser()) && hash_equals($password, (string) $request->getPassword());
    }
}
