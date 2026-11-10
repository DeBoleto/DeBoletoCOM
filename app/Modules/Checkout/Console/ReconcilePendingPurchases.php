<?php

namespace App\Modules\Checkout\Console;

use App\Modules\Checkout\Services\PaymentProcessor;
use App\Modules\Checkout\Services\PendingPurchaseWriter;
use App\Modules\Checkout\Services\PurchaseFinalizer;
use App\Modules\Checkout\Services\SeatHolds;
use App\Modules\Orders\Enums\PaymentType;
use App\Modules\Orders\Enums\PurchaseStatus;
use App\Modules\Orders\Models\EventosCompras;
use App\Modules\Payments\Exceptions\PaymentException;
use App\Modules\Shared\Models\LegacyModel;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Revisa las compras web que siguen esperando pago: si el cargo se completó
 * las cierra; si venció el tiempo, libera sus asientos. También borra las
 * retenciones de asientos caducadas.
 */
class ReconcilePendingPurchases extends Command
{
    protected $signature = 'checkout:reconcile';

    protected $description = 'Cierra o libera las compras web pendientes de pago y limpia retenciones vencidas';

    public function handle(PaymentProcessor $payments, PurchaseFinalizer $finalizer, SeatHolds $holds): int
    {
        $now = Carbon::now(LegacyModel::legacyTimezone());

        $this->pending(PurchaseStatus::AwaitingPayment, $now->copy()->subMinutes((int) config('checkout.card_payment_minutes')))
            ->merge($this->pending(PurchaseStatus::AwaitingTransfer, $now->copy()->subHours((int) config('checkout.transfer_hours'))->subMinutes(15)))
            ->each(function (EventosCompras $purchase) use ($payments, $finalizer) {
                if (($finalizer->snapshot($purchase)['source'] ?? null) !== PendingPurchaseWriter::SOURCE) {
                    return;
                }

                if (! $purchase->pk_openpay) {
                    $finalizer->release($purchase);

                    return;
                }

                try {
                    $charge = $payments->sync($purchase);
                } catch (PaymentException $exception) {
                    $this->warn("Compra {$purchase->pk_evento_compra}: {$exception->getMessage()}");

                    return;
                }

                if (! $charge->isPaid()) {
                    $finalizer->release($purchase, PurchaseStatus::Rejected);
                }
            });

        $purged = $holds->purgeExpired();
        $this->info("Retenciones vencidas eliminadas: {$purged}");

        return self::SUCCESS;
    }

    private function pending(PurchaseStatus $status, Carbon $createdBefore)
    {
        return EventosCompras::query()
            ->where('pk_evento_compra_tipo', PaymentType::Web->value)
            ->where('estatus', $status->value)
            ->where('creacion_fecha', '<', $createdBefore)
            ->orderBy('pk_evento_compra')
            ->limit(200)
            ->get();
    }
}
