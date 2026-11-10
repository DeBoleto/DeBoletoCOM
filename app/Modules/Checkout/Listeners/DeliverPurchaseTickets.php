<?php

namespace App\Modules\Checkout\Listeners;

use App\Modules\BoxOffice\Mail\DigitalTicketsMail;
use App\Modules\BoxOffice\Services\Delivery\WhatsAppTicketSender;
use App\Modules\BoxOffice\Services\Printing\TicketPrintService;
use App\Modules\Checkout\Events\WebPurchasePaid;
use App\Modules\Orders\Models\EventosCompras;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envía los boletos digitales por correo y, si el cliente lo pidió, el enlace por WhatsApp.
 */
class DeliverPurchaseTickets implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(
        private readonly TicketPrintService $printing,
        private readonly WhatsAppTicketSender $whatsapp,
    ) {}

    public function handle(WebPurchasePaid $event): void
    {
        $purchase = EventosCompras::query()->with(['usuario', 'evento'])->findOrFail($event->purchaseId);

        $pdf = $this->printing->digitalDocument($purchase)->output();
        Mail::to($purchase->usuario->correo)->send(new DigitalTicketsMail($purchase->evento->evento, $purchase->pk_evento_compra, $pdf));

        if ($event->whatsappPhone) {
            try {
                $this->whatsapp->sendLink($purchase, $event->whatsappPhone);
            } catch (Throwable $exception) {
                Log::warning('No se pudo enviar la compra web por WhatsApp', ['compra' => $purchase->pk_evento_compra, 'message' => $exception->getMessage()]);
            }
        }
    }
}
