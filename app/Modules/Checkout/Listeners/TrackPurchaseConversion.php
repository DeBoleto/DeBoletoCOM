<?php

namespace App\Modules\Checkout\Listeners;

use App\Modules\Checkout\Events\WebPurchasePaid;
use App\Modules\Checkout\Jobs\SendConversionEvent;
use App\Modules\Checkout\Tracking\MetaTracker;
use App\Modules\Orders\Models\EventosCompras;

class TrackPurchaseConversion
{
    public function handle(WebPurchasePaid $event): void
    {
        $purchase = EventosCompras::query()->with('usuario')->findOrFail($event->purchaseId);
        $customer = $purchase->usuario;

        SendConversionEvent::dispatch(
            $purchase->pk_evento,
            'Purchase',
            route('checkout.complete', $purchase->codigo),
            MetaTracker::rememberedContext((int) $purchase->pk_usuario, (int) $purchase->pk_evento)
                + ($customer ? MetaTracker::customerData($customer) : []),
            self::customData($purchase),
            self::eventId($purchase),
        );
    }

    /**
     * El navegador manda el mismo Purchase desde la página de la compra.
     */
    public static function eventId(EventosCompras $purchase): string
    {
        return 'purchase-'.$purchase->codigo;
    }

    /**
     * @return array<string, mixed>
     */
    public static function customData(EventosCompras $purchase): array
    {
        return [
            'content_ids' => [(string) $purchase->pk_evento],
            'content_type' => 'product',
            'currency' => 'MXN',
            'value' => round((float) $purchase->total_compra + (float) $purchase->total_comision_aplicacion, 2),
            'order_id' => (string) $purchase->codigo,
        ];
    }
}
