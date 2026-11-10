<?php

namespace App\Modules\Checkout\Listeners;

use App\Modules\Checkout\Events\WebPurchasePaid;
use App\Modules\Checkout\Jobs\SendConversionEvent;
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
            SendConversionEvent::hashUserData([
                'em' => $customer->correo,
                'ph' => $customer->telefono,
                'fn' => $customer->nombre,
                'ln' => trim($customer->apellido_paterno.' '.$customer->apellido_materno),
            ]),
            $purchase->total_compra + $purchase->total_comision_aplicacion,
        );
    }
}
