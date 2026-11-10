<?php

namespace App\Modules\Checkout\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BoxOffice\Services\Printing\TicketPrintService;
use App\Modules\Checkout\Http\CheckoutPresenter;
use App\Modules\Checkout\Services\PaymentProcessor;
use App\Modules\Checkout\Services\PendingPurchaseWriter;
use App\Modules\Checkout\Services\PurchaseFinalizer;
use App\Modules\Events\Models\Views\EventoFuncionVista;
use App\Modules\Events\Models\Views\EventoVista;
use App\Modules\Orders\Enums\PaymentType;
use App\Modules\Orders\Enums\PurchaseStatus;
use App\Modules\Orders\Models\EventosCompras;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Data\Charge;
use App\Modules\Payments\Exceptions\PaymentException;
use App\Modules\Tickets\Models\EventosFuncionesBoletos;
use App\Modules\Users\Models\Usuarios;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resultado de una compra web: boletos emitidos, pago en proceso o
 * instrucciones de transferencia SPEI. Solo la ve el dueño de la compra.
 */
class OrderController extends Controller
{
    public function show(PaymentProcessor $payments, PaymentGateway $gateway, PurchaseFinalizer $finalizer, string $codigo): Response|RedirectResponse
    {
        $purchase = $this->ownedPurchase($codigo, $finalizer);

        if ($purchase->status() === PurchaseStatus::AwaitingPayment && $purchase->pk_openpay) {
            $this->syncAtMostEveryFewSeconds($payments, $purchase);
            $purchase->refresh();
        }

        $event = EventoVista::query()->findOrFail($purchase->pk_evento);
        $function = EventoFuncionVista::query()->find($purchase->pk_evento_funcion);
        $status = $purchase->status() ?? PurchaseStatus::Rejected;
        $snapshot = $finalizer->snapshot($purchase);

        return Inertia::render('Checkout/Complete', [
            'event' => CheckoutPresenter::event($event),
            'function' => $function ? CheckoutPresenter::function($function) : null,
            'order' => [
                'code' => $purchase->codigo,
                'number' => $purchase->pk_evento_compra,
                'status' => PaymentProcessor::statusLabel($status),
                'total' => (float) ($snapshot['amount'] ?? 0),
                'quote' => $snapshot['quote'] ?? null,
                'tickets' => $purchase->boletos()->with('localidad:pk_evento_localidad,evento_localidad')->get()
                    ->map(fn (EventosFuncionesBoletos $ticket, int $index) => [
                        'label' => $ticket->isGeneralAdmission() ? 'Boleto '.($index + 1) : $ticket->asiento,
                        'locality' => $ticket->localidad?->evento_localidad,
                        'courtesy' => (bool) $ticket->cortesia,
                    ])->values(),
                'transfer' => $status === PurchaseStatus::AwaitingTransfer ? $this->transferInstructions($gateway, $purchase) : null,
                'downloadUrl' => $status === PurchaseStatus::Paid ? route('checkout.tickets', $purchase->codigo) : null,
                'digitalUrl' => $status === PurchaseStatus::Paid ? config('boxoffice.digital_ticket_url').$purchase->codigo : null,
                'bondUrl' => $status === PurchaseStatus::Paid && (int) $event->bono === 1 ? config('boxoffice.bond_registration_url').$purchase->codigo : null,
                'email' => $purchase->usuario?->correo,
            ],
        ]);
    }

    public function tickets(TicketPrintService $printing, PurchaseFinalizer $finalizer, string $codigo): HttpResponse
    {
        $purchase = $this->ownedPurchase($codigo, $finalizer);

        abort_unless($purchase->status() === PurchaseStatus::Paid, 404);

        return $printing->digitalDocument($purchase)->download('deboleto-compra-'.$purchase->pk_evento_compra.'.pdf');
    }

    private function ownedPurchase(string $code, PurchaseFinalizer $finalizer): EventosCompras
    {
        $customer = Auth::guard('customer')->user();
        abort_unless($customer instanceof Usuarios, 404);

        $purchase = EventosCompras::query()
            ->where('codigo', $code)
            ->where('pk_usuario', $customer->pk_usuario)
            ->where('pk_evento_compra_tipo', PaymentType::Web->value)
            ->first();

        abort_if($purchase === null || ($finalizer->snapshot($purchase)['source'] ?? null) !== PendingPurchaseWriter::SOURCE, 404);

        return $purchase;
    }

    private function syncAtMostEveryFewSeconds(PaymentProcessor $payments, EventosCompras $purchase): void
    {
        if (! Cache::add("checkout:sync:{$purchase->pk_evento_compra}", true, now()->addSeconds(5))) {
            return;
        }

        try {
            $payments->sync($purchase);
        } catch (PaymentException) {
            // La página vuelve a consultar; el webhook o la tarea programada cierran la compra.
        }
    }

    private function transferInstructions(PaymentGateway $gateway, EventosCompras $purchase): ?array
    {
        if (! $purchase->pk_openpay) {
            return null;
        }

        $charge = Cache::remember("checkout:transfer:{$purchase->pk_openpay}", now()->addMinutes(10), function () use ($gateway, $purchase): ?Charge {
            try {
                return $gateway->charge((string) $purchase->pk_openpay);
            } catch (PaymentException) {
                return null;
            }
        });

        if ($charge?->transfer === null) {
            return null;
        }

        return $charge->transfer->toArray() + [
            'amount' => $charge->amount,
            'dueDate' => $charge->dueDate?->toIso8601String(),
            'reference' => $purchase->codigo,
        ];
    }
}
