<?php

namespace App\Modules\Checkout\Listeners;

use App\Modules\Checkout\Events\WebTransferRequested;
use App\Modules\Checkout\Mail\TransferInstructionsMail;
use App\Modules\Orders\Models\EventosCompras;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

/**
 * Envía por correo la CLABE y el monto para pagar una compra web por SPEI.
 */
class SendTransferInstructions implements ShouldQueue
{
    public int $tries = 3;

    public function handle(WebTransferRequested $event): void
    {
        $purchase = EventosCompras::query()->with(['usuario', 'evento'])->findOrFail($event->purchaseId);

        Mail::to($purchase->usuario->correo)->send(new TransferInstructionsMail(
            (string) $purchase->evento?->evento,
            $purchase->codigo,
            $event->amount,
            $event->transfer->clabe,
            $event->transfer->bank,
            $event->transfer->beneficiary,
            $event->dueDate,
            route('checkout.complete', $purchase->codigo),
        ));
    }
}
