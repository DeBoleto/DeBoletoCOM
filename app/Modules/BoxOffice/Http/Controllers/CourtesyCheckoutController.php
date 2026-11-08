<?php

namespace App\Modules\BoxOffice\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BoxOffice\Exceptions\SeatsUnavailableException;
use App\Modules\BoxOffice\Http\Requests\CreateCourtesiesRequest;
use App\Modules\BoxOffice\Http\Requests\PickSeatsRequest;
use App\Modules\BoxOffice\Policies\BoxOfficeAbilities;
use App\Modules\BoxOffice\Services\Checkout\CheckoutState;
use App\Modules\BoxOffice\Services\Checkout\CheckoutStore;
use App\Modules\BoxOffice\Services\Checkout\ConfirmationGuard;
use App\Modules\BoxOffice\Services\Checkout\PurchaseService;
use App\Modules\BoxOffice\Services\Checkout\SaleCatalog;
use App\Modules\BoxOffice\Services\Checkout\SeatPickerProps;
use App\Modules\BoxOffice\Services\Checkout\SeatSelectionResolver;
use App\Modules\BoxOffice\Services\Checkout\TicketLine;
use App\Modules\BoxOffice\Services\Printing\TicketPrintService;
use App\Modules\BoxOffice\Support\EventImage;
use App\Modules\Events\Models\Eventos;
use App\Modules\Events\Models\Views\EventoFuncionVista;
use App\Modules\Events\Models\Views\EventoVista;
use App\Modules\Events\Models\Views\UsuarioEventoVista;
use App\Modules\Orders\Enums\PaymentType;
use App\Modules\Orders\Models\EventosCompras;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Creación de cortesías: evento → función y asientos → confirmación → impresión.
 */
class CourtesyCheckoutController extends Controller
{
    private readonly CheckoutStore $store;

    public function __construct(CheckoutStore $store, private readonly SaleCatalog $catalog)
    {
        $this->store = $store->for('courtesy');
    }

    public function events(Request $request): Response
    {
        $events = UsuarioEventoVista::query()
            ->delUsuario($request->user()->pk_usuario)
            ->whereIn('pk_evento_status', [Eventos::STATUS_DRAFT, Eventos::STATUS_PUBLISHED])
            ->where('fin_fecha', '>=', now(config('boxoffice.timezone'))->toDateString())
            ->orderBy('inicio_fecha')
            ->get(['pk_evento', 'evento', 'imagen', 'escenario', 'ciudad', 'estado', 'inicio_fecha_natural']);

        return Inertia::render('BoxOffice/Tickets/Index', [
            'title' => 'Crear cortesías',
            'actionLabel' => 'Crear cortesías',
            'actionRoute' => 'boxoffice.courtesies.create.selection',
            'events' => $events->map(fn (UsuarioEventoVista $event) => [
                'id' => $event->pk_evento,
                'name' => $event->evento,
                'image' => EventImage::url($event->imagen),
                'venue' => $event->escenario,
                'city' => $event->ciudad,
                'state' => $event->estado,
                'date' => $event->inicio_fecha_natural,
            ])->values(),
        ]);
    }

    public function selection(SeatPickerProps $props, int $evento): Response
    {
        $event = $this->event($evento);
        $this->store->forget($evento);

        return Inertia::render('BoxOffice/Shared/PickSeats', $props->for($event) + [
            'title' => 'Crear cortesías',
            'submitRoute' => 'boxoffice.courtesies.create.selection.store',
            'stageRoute' => 'boxoffice.courtesies.create.stage',
            'cancelRoute' => 'boxoffice.courtesies.create.index',
            'submitLabel' => 'Revisar cortesías',
            'showPrices' => false,
        ]);
    }

    public function storeSelection(PickSeatsRequest $request, SeatSelectionResolver $resolver, int $evento): RedirectResponse
    {
        $event = $this->event($evento);
        $functionId = (int) $request->validated('funcion');

        if (! $this->catalog->functionBelongsToEvent($functionId, $evento)) {
            throw ValidationException::withMessages(['funcion' => 'La función no pertenece a este evento.']);
        }

        ['locality' => $locality, 'section' => $section, 'seats' => $seats] = $resolver->resolve($event, $functionId, $request->validated());

        $lines = array_map(
            fn (string $seat, int $index) => TicketLine::courtesy(
                $locality->pk_evento_localidad,
                $locality->evento_localidad,
                $seat === 'NA_NA' ? 'NA_NA'.$index : $seat,
            ),
            $seats,
            array_keys($seats),
        );

        $this->store->put(new CheckoutState(
            $evento, $functionId, PaymentType::Courtesy, $lines,
            $locality->pk_evento_localidad, $section['sectionId'], $section['capacity'],
        ));

        return redirect()->route('boxoffice.courtesies.create.confirm', $evento);
    }

    public function confirm(int $evento): Response|RedirectResponse
    {
        $event = $this->event($evento);
        $state = $this->store->get($evento);

        if ($state === null) {
            return redirect()->route('boxoffice.courtesies.create.selection', $evento);
        }

        if ($state->isPurchased()) {
            return redirect()->route('boxoffice.courtesies.create.done', $evento);
        }

        $function = EventoFuncionVista::query()->findOrFail($state->functionId);

        return Inertia::render('BoxOffice/Courtesies/Confirm', [
            'event' => SeatPickerProps::event($event),
            'function' => ['date' => $function->fecha_natural, 'time' => $function->hora_natural],
            'lines' => array_map(fn (TicketLine $line, int $index) => [
                'label' => $line->isGeneralAdmission() ? 'Boleto '.($index + 1) : $line->seat,
                'locality' => $line->localityName,
            ], $state->lines, array_keys($state->lines)),
            'confirmToken' => (string) Str::uuid(),
        ]);
    }

    public function store(CreateCourtesiesRequest $request, PurchaseService $purchases, ConfirmationGuard $guard, int $evento): RedirectResponse
    {
        $event = $this->event($evento);
        $state = $this->store->get($evento);

        if ($state === null) {
            return redirect()->route('boxoffice.courtesies.create.selection', $evento);
        }

        $cost = (float) $request->validated('costo');
        $priced = new CheckoutState(
            $state->eventId, $state->functionId, PaymentType::Courtesy,
            array_map(fn (TicketLine $line) => new TicketLine($line->localityId, $line->localityName, $line->seat, $cost, courtesy: true), $state->lines),
            $state->localityId, $state->sectionId, $state->capacity,
        );

        try {
            $purchaseId = $guard->once(
                "{$request->user()->pk_usuario}:courtesy:{$evento}:{$request->validated('token')}",
                fn () => $purchases->purchase($priced, $event, $request->user(), $request->validated('comentario'), expandTables: false)->pk_evento_compra,
            );
        } catch (SeatsUnavailableException $exception) {
            return redirect()->route('boxoffice.courtesies.create.selection', $evento)->with('error', $exception->getMessage());
        }

        if ($purchaseId === ConfirmationGuard::IN_PROGRESS) {
            return back()->with('error', 'Las cortesías ya se están generando. Espera un momento.');
        }

        $this->store->put($priced->withPurchase($purchaseId));

        return redirect()->route('boxoffice.courtesies.create.done', $evento);
    }

    public function done(TicketPrintService $printing, int $evento): Response|RedirectResponse
    {
        $event = $this->event($evento);
        $state = $this->store->get($evento);

        if ($state === null || ! $state->isPurchased()) {
            return redirect()->route('boxoffice.courtesies.create.selection', $evento);
        }

        return Inertia::render('BoxOffice/Shared/Delivered', [
            'title' => 'Cortesías creadas',
            'event' => SeatPickerProps::event($event),
            'purchaseId' => $state->purchaseId,
            'tickets' => count($state->lines),
            'printRoute' => 'boxoffice.courtesies.create.print',
            'formats' => $printing->catalog(),
            'defaultFormat' => $event->pk_boleto_formato_tipo,
            'finishRoute' => 'boxoffice.courtesies.create.finish',
        ]);
    }

    public function print(Request $request, TicketPrintService $printing, int $evento): View
    {
        $event = $this->event($evento);
        $state = $this->store->get($evento);
        abort_unless($state?->isPurchased(), 404);

        $tickets = EventosCompras::query()->findOrFail($state->purchaseId)->boletos()->get();
        $format = $request->integer('formato') ?: $event->pk_boleto_formato_tipo;

        return $printing->printCourtesies($tickets, $request->user(), $format);
    }

    public function finish(int $evento): RedirectResponse
    {
        $this->store->forget($evento);

        return redirect()->route('boxoffice.courtesies.create.index')->with('success', 'Cortesías listas.');
    }

    private function event(int $eventId): EventoVista
    {
        Gate::authorize(BoxOfficeAbilities::EVENT, $eventId);

        return $this->catalog->event($eventId);
    }
}
