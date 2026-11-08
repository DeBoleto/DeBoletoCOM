<?php

namespace App\Modules\BoxOffice\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BoxOffice\Policies\BoxOfficeAbilities;
use App\Modules\BoxOffice\Services\Printing\TicketPrintService;
use App\Modules\Events\Models\Eventos;
use App\Modules\Events\Models\Views\EventoFuncionVista;
use App\Modules\Events\Models\Views\UsuarioEventoVista;
use App\Modules\Orders\Models\EventosCompras;
use App\Modules\Tickets\Models\EventosFuncionesBoletos;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Consulta, impresión y baja de boletos de cortesía.
 */
class CourtesyController extends Controller
{
    public function __construct(private readonly TicketPrintService $printing) {}

    public function index(Request $request): Response
    {
        $eventId = $request->integer('evento') ?: null;

        if ($eventId !== null) {
            Gate::authorize(BoxOfficeAbilities::EVENT, $eventId);
        }

        return Inertia::render('BoxOffice/Courtesies/Index', [
            'events' => UsuarioEventoVista::query()
                ->delUsuario($request->user()->pk_usuario)
                ->where('pk_evento_status', Eventos::STATUS_PUBLISHED)
                ->where('fin_fecha', '>=', now(config('boxoffice.timezone'))->toDateString())
                ->orderBy('evento')
                ->get(['pk_evento', 'evento', 'inicio_fecha_natural'])
                ->map(fn (UsuarioEventoVista $event) => [
                    'id' => $event->pk_evento,
                    'name' => $event->evento,
                    'date' => $event->inicio_fecha_natural,
                ])->values(),
            'selectedEvent' => $eventId,
            'tickets' => $eventId ? $this->tickets($eventId) : [],
        ]);
    }

    public function print(Request $request, int $boleto): View
    {
        $ticket = $this->courtesy($boleto);

        return $this->printing->printCourtesies(collect([$ticket]), $request->user());
    }

    public function printPending(Request $request, int $evento): View|RedirectResponse
    {
        Gate::authorize(BoxOfficeAbilities::EVENT, $evento);

        $tickets = EventosFuncionesBoletos::query()
            ->visibles()
            ->where('pk_evento', $evento)
            ->where('cortesia', 1)
            ->where('impresion', 0)
            ->get();

        if ($tickets->isEmpty()) {
            return back()->with('error', 'No hay cortesías pendientes de imprimir en este evento.');
        }

        return $this->printing->printCourtesies($tickets, $request->user());
    }

    public function destroy(int $boleto): RedirectResponse
    {
        $ticket = $this->courtesy($boleto);

        DB::transaction(function () use ($ticket) {
            $purchaseId = $ticket->pk_evento_compra;
            $ticket->delete();

            if (! EventosFuncionesBoletos::query()->where('pk_evento_compra', $purchaseId)->exists()) {
                EventosCompras::query()->whereKey($purchaseId)->delete();
            }
        });

        return back()->with('success', "La cortesía {$ticket->pk_evento_funcion_boleto} se eliminó.");
    }

    private function courtesy(int $ticketId): EventosFuncionesBoletos
    {
        $ticket = EventosFuncionesBoletos::query()->where('cortesia', 1)->findOrFail($ticketId);

        Gate::authorize(BoxOfficeAbilities::EVENT, $ticket->pk_evento);

        return $ticket;
    }

    private function tickets(int $eventId): array
    {
        $tickets = EventosFuncionesBoletos::query()
            ->with('localidad:pk_evento_localidad,evento_localidad')
            ->visibles()
            ->where('pk_evento', $eventId)
            ->where('cortesia', 1)
            ->orderBy('asiento')
            ->orderBy('pk_evento_funcion')
            ->get();

        $functions = EventoFuncionVista::query()
            ->whereIn('pk_evento_funcion', $tickets->pluck('pk_evento_funcion')->unique())
            ->get()
            ->keyBy('pk_evento_funcion');

        return $tickets->map(fn (EventosFuncionesBoletos $ticket) => [
            'id' => $ticket->pk_evento_funcion_boleto,
            'purchaseId' => $ticket->pk_evento_compra,
            'seat' => $ticket->isGeneralAdmission() ? null : $ticket->asiento,
            'locality' => $ticket->localidad?->evento_localidad,
            'function' => ($function = $functions->get($ticket->pk_evento_funcion))
                ? "{$function->fecha_natural} {$function->hora_natural} h"
                : null,
            'printed' => $ticket->impresion,
            'scanned' => (bool) ($ticket->escaneado ?? false),
        ])->values()->all();
    }
}
