<?php

namespace App\Modules\BoxOffice\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BoxOffice\Http\Requests\SalesRangeRequest;
use App\Modules\BoxOffice\Http\Requests\SellerCutRequest;
use App\Modules\BoxOffice\Http\Requests\SendTicketsMailRequest;
use App\Modules\BoxOffice\Mail\DigitalTicketsMail;
use App\Modules\BoxOffice\Policies\BoxOfficeAbilities;
use App\Modules\BoxOffice\Services\Printing\TicketPrintService;
use App\Modules\BoxOffice\Services\Sales\SalesReportBuilder;
use App\Modules\BoxOffice\Support\SpanishDate;
use App\Modules\Events\Models\Eventos;
use App\Modules\Events\Models\Views\EventoVista;
use App\Modules\Orders\Models\EventosCompras;
use App\Modules\Users\Models\Usuarios;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class SalesController extends Controller
{
    public function __construct(private readonly SalesReportBuilder $reports) {}

    public function index(Request $request): Response
    {
        $events = $request->user()->eventos()
            ->publicados()
            ->with('escenario:pk_escenario,escenario,pk_ciudad', 'escenario.ciudad:pk_ciudad,pk_estado,ciudad', 'escenario.ciudad.estado:pk_estado,estado')
            ->orderByDesc('inicio_fecha')
            ->get(['eventos.pk_evento', 'eventos.pk_escenario', 'eventos.evento', 'eventos.inicio_fecha']);

        return Inertia::render('BoxOffice/Sales/Index', [
            'events' => $events->map(fn (Eventos $event) => [
                'id' => $event->pk_evento,
                'name' => $event->evento,
                'date' => $event->inicio_fecha?->toDateString(),
                'venue' => $event->escenario?->escenario,
                'city' => $event->escenario?->ciudad?->ciudad,
                'state' => $event->escenario?->ciudad?->estado?->estado,
            ])->values(),
            'today' => now(config('boxoffice.timezone'))->toDateString(),
        ]);
    }

    public function cashCut(Request $request, int $evento): HttpResponse
    {
        $event = $this->event($evento);
        $seller = $request->user();
        $today = now(config('boxoffice.timezone'))->startOfDay();

        return $this->pdf('boxoffice.pdf.cash-cut', [
            'title' => 'Corte de caja',
            'event' => $event,
            'seller' => $seller,
            'period' => SpanishDate::long($today),
            'functions' => $this->reports->cashCut($event, $seller->pk_usuario, $today),
        ], "corte-caja-{$evento}");
    }

    public function sellerCut(SellerCutRequest $request, int $evento, int $usuario): HttpResponse
    {
        $event = $this->event($evento);
        $seller = Usuarios::query()->findOrFail($usuario);

        abort_unless(
            $seller->pk_usuario === $request->user()->pk_usuario || BoxOfficeAbilities::accessEvent($seller, $evento),
            404,
        );

        return $this->pdf('boxoffice.pdf.cash-cut', [
            'title' => 'Corte de caja por usuario',
            'event' => $event,
            'seller' => $seller,
            'period' => SpanishDate::long($request->cutDate()),
            'functions' => $this->reports->sellerCut($event, $seller->pk_usuario, $request->cutDate()),
        ], "corte-usuario-{$evento}-{$usuario}");
    }

    public function salesByDate(SalesRangeRequest $request, int $evento): HttpResponse
    {
        $event = $this->event($evento);
        $from = $request->from();
        $to = $request->to();

        return $this->pdf('boxoffice.pdf.sales-by-date', [
            'title' => 'Ventas por fecha',
            'event' => $event,
            'seller' => $request->user(),
            'period' => SpanishDate::long($from).' — '.SpanishDate::long($to),
            'functions' => $this->reports->salesByDate($event, $request->user()->pk_usuario, $from, $to),
        ], "ventas-{$evento}-{$from->toDateString()}-{$to->toDateString()}");
    }

    public function mailForm(): Response
    {
        return Inertia::render('BoxOffice/Sales/SendTickets');
    }

    public function sendMail(SendTicketsMailRequest $request, TicketPrintService $printing): RedirectResponse
    {
        $purchase = EventosCompras::query()->find($request->validated('folio'));

        if ($purchase === null || Gate::denies(BoxOfficeAbilities::PURCHASE, $purchase)) {
            return back()->withErrors(['folio' => 'No encontramos una compra con ese folio en tus eventos.']);
        }

        $event = EventoVista::query()->findOrFail($purchase->pk_evento);
        $pdf = $printing->digitalDocument($purchase)->output();

        Mail::to($request->validated('email'))->send(new DigitalTicketsMail($event->evento, $purchase->pk_evento_compra, $pdf));

        return back()->with('success', "Boletos de la compra {$purchase->pk_evento_compra} ({$event->evento}) enviados a {$request->validated('email')}.");
    }

    private function event(int $eventId): EventoVista
    {
        Gate::authorize(BoxOfficeAbilities::EVENT, $eventId);

        return EventoVista::query()->findOrFail($eventId);
    }

    private function pdf(string $view, array $data, string $filename): HttpResponse
    {
        $data['generatedAt'] = Carbon::now(config('boxoffice.timezone'));

        return Pdf::loadView($view, $data)->setPaper('letter')->stream($filename.'.pdf');
    }
}
