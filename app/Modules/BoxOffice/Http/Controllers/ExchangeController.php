<?php

namespace App\Modules\BoxOffice\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BoxOffice\Policies\BoxOfficeAbilities;
use App\Modules\BoxOffice\Services\Printing\TicketPresenter;
use App\Modules\BoxOffice\Services\Printing\TicketPrintService;
use App\Modules\Events\Models\Views\EventoFuncionVista;
use App\Modules\Events\Models\Views\EventoVista;
use App\Modules\Orders\Enums\PaymentType;
use App\Modules\Orders\Models\EventosCompras;
use App\Modules\Tickets\Models\EventosFuncionesBoletos;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Canje en taquilla: el cliente presenta el folio de una compra (web u otra
 * taquilla) y se le imprimen sus boletos una sola vez.
 */
class ExchangeController extends Controller
{
    public function __construct(private readonly TicketPrintService $printing) {}

    public function index(Request $request): Response
    {
        $folio = $request->integer('folio') ?: null;

        return Inertia::render('BoxOffice/Exchange/Index', [
            'folio' => $folio,
            'purchase' => $folio ? $this->lookup($folio) : null,
        ]);
    }

    public function print(Request $request, int $compra): View|RedirectResponse
    {
        $purchase = $this->purchase($compra);

        if ($this->allPrinted($purchase)) {
            return redirect()->route('boxoffice.exchange.index', ['folio' => $compra])
                ->with('error', 'Los boletos de esta compra ya fueron impresos.');
        }

        return $this->printing->printView($purchase, $request->user(), TicketPresenter::EXCHANGE);
    }

    public function digital(Request $request, int $compra): HttpResponse
    {
        return $this->printing->digitalPdf($this->purchase($compra), $request->user(), TicketPresenter::DIGITAL, printed: true);
    }

    private function lookup(int $folio): array
    {
        $purchase = EventosCompras::query()->find($folio);

        if ($purchase === null || Gate::denies(BoxOfficeAbilities::PURCHASE, $purchase)) {
            return ['found' => false, 'message' => 'No hay una compra con ese folio en tus eventos.'];
        }

        $tickets = $purchase->boletos()->visibles()->with('localidad:pk_evento_localidad,evento_localidad')->get();

        if ($tickets->isEmpty()) {
            return ['found' => false, 'message' => 'La compra no tiene boletos.'];
        }

        $event = EventoVista::query()->find($purchase->pk_evento);
        $function = EventoFuncionVista::query()->find($tickets->first()->pk_evento_funcion);

        return [
            'found' => true,
            'id' => $purchase->pk_evento_compra,
            'event' => $event?->evento,
            'function' => $function ? "{$function->fecha_natural} {$function->hora_natural} h" : null,
            'payment' => PaymentType::tryFrom((int) $purchase->pk_evento_compra_tipo)?->label(),
            'total' => $purchase->total_compra,
            'allPrinted' => $tickets->every(fn (EventosFuncionesBoletos $ticket) => $ticket->impresion),
            'tickets' => $tickets->map(fn (EventosFuncionesBoletos $ticket) => [
                'id' => $ticket->pk_evento_funcion_boleto,
                'seat' => $ticket->isGeneralAdmission() ? null : $ticket->asiento,
                'locality' => $ticket->localidad?->evento_localidad,
                'courtesy' => $ticket->cortesia,
                'printed' => $ticket->impresion,
            ])->values(),
        ];
    }

    private function allPrinted(EventosCompras $purchase): bool
    {
        return ! $purchase->boletos()->visibles()->where('impresion', 0)->exists();
    }

    private function purchase(int $purchaseId): EventosCompras
    {
        $purchase = EventosCompras::query()->findOrFail($purchaseId);
        Gate::authorize(BoxOfficeAbilities::PURCHASE, $purchase);

        return $purchase;
    }
}
