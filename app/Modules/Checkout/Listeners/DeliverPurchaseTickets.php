<?php

namespace App\Modules\Checkout\Listeners;

use App\Modules\BoxOffice\Services\Delivery\WhatsAppTicketSender;
use App\Modules\Checkout\Events\WebPurchasePaid;
use App\Modules\Checkout\Mail\PurchaseTicketsMail;
use App\Modules\Orders\Models\EventosCompras;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envía por correo el enlace a los boletos digitales y, si el cliente lo pidió, también por WhatsApp.
 */
class DeliverPurchaseTickets implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly WhatsAppTicketSender $whatsapp) {}

    public function handle(WebPurchasePaid $event): void
    {
        $purchase = EventosCompras::query()->with(['usuario', 'evento'])->findOrFail($event->purchaseId);

        Mail::to($purchase->usuario->correo)->send(new PurchaseTicketsMail(
            (string) $purchase->evento?->evento,
            $purchase->codigo,
            route('ticket.shared', $purchase->codigo),
        ));

        if ($event->whatsappPhone) {
            try {
                $this->whatsapp->sendLink($purchase, $event->whatsappPhone);
            } catch (Throwable $exception) {
                Log::warning('No se pudo enviar la compra web por WhatsApp', ['compra' => $purchase->pk_evento_compra, 'message' => $exception->getMessage()]);
            }
        }
    }
}
