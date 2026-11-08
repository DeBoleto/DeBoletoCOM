<?php

namespace App\Modules\BoxOffice\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BoxOffice\Exceptions\SeatsUnavailableException;
use App\Modules\BoxOffice\Http\Requests\ConfirmPurchaseRequest;
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
use App\Modules\BoxOffice\Services\Printing\TicketPresenter;
use App\Modules\BoxOffice\Services\Printing\TicketPrintService;
use App\Modules\BoxOffice\Support\EventImage;
use App\Modules\Events\Enums\CommissionType;
use App\Modules\Events\Models\EventoLocalidadAsiento;
use App\Modules\Events\Models\Eventos;
use App\Modules\Events\Models\EventosLocalidades;
use App\Modules\Events\Models\Views\EventoFuncionVista;
use App\Modules\Events\Models\Views\EventoVista;
use App\Modules\Events\Models\Views\UsuarioEventoVista;
use App\Modules\Orders\Enums\PaymentType;
use App\Modules\Orders\Models\EventosCompras;
use App\Modules\Orders\Models\EventosComprasTipos;
use App\Modules\Tickets\Enums\TicketStatus;
use App\Modules\Tickets\Models\BoletosCanjeados;
use App\Modules\Tickets\Models\EventosFuncionesBoletos;
use App\Modules\Users\Models\Usuarios;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cambio de boletos de un evento cancelado por boletos de otro evento,
 * cobrando la diferencia.
 */
class TicketChangeController extends Controller
{
    private const OLD_TICKET = 'ticket';

    private const PAYMENT = 'payment';

    private readonly CheckoutStore $store;

    public function __construct(CheckoutStore $store, private readonly SaleCatalog $catalog)
    {
        $this->store = $store->for('change');
    }

    public function index(): Response
    {
        $this->store->forgetValue(self::OLD_TICKET);

        return Inertia::render('BoxOffice/Change/Index');
    }

    public function lookup(Request $request): RedirectResponse
    {
        $folio = (int) ltrim((string) $request->validate(['folio' => ['required', 'regex:/^\d{1,12}$/']])['folio'], '0');
        $ticket = EventosFuncionesBoletos::query()->visibles()->find($folio);

        if ($ticket === null || Gate::denies(BoxOfficeAbilities::EVENT, $ticket->pk_evento)) {
            throw ValidationException::withMessages(['folio' => 'No encontramos ese boleto en tus eventos.']);
        }

        if ($this->alreadyChanged($ticket)) {
            throw ValidationException::withMessages(['folio' => 'Este boleto ya fue cambiado.']);
        }

        $this->store->putValue(self::OLD_TICKET, $ticket->pk_evento_funcion_boleto);

        return redirect()->route('boxoffice.change.events');
    }

    public function events(Request $request): Response|RedirectResponse
    {
        if (! $this->oldTicket()) {
            return redirect()->route('boxoffice.change.index');
        }

        $events = UsuarioEventoVista::query()
            ->delUsuario($request->user()->pk_usuario)
            ->where('pk_evento_status', Eventos::STATUS_PUBLISHED)
            ->where('pk_evento_categoria', config('boxoffice.change_target_category'))
            ->where('fin_fecha', '>=', now(config('boxoffice.timezone'))->toDateString())
            ->orderBy('inicio_fecha')
            ->get(['pk_evento', 'evento', 'imagen', 'escenario', 'ciudad', 'estado', 'inicio_fecha_natural']);

        return Inertia::render('BoxOffice/Tickets/Index', [
            'title' => 'Cambio de boleto: elige el nuevo evento',
            'actionLabel' => 'Cambiar a este evento',
            'actionRoute' => 'boxoffice.change.selection',
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

    public function selection(Request $request, SeatPickerProps $props, int $evento): Response|RedirectResponse
    {
        $old = $this->oldTicket();

        if (! $old) {
            return redirect()->route('boxoffice.change.index');
        }

        $event = $this->event($evento);

        return Inertia::render('BoxOffice/Shared/PickSeats', $props->for($event) + [
            'title' => 'Cambio de boleto',
            'submitRoute' => 'boxoffice.change.selection.store',
            'stageRoute' => 'boxoffice.change.stage',
            'cancelRoute' => 'boxoffice.change.index',
            'submitLabel' => 'Calcular diferencia',
            'showPrices' => true,
            'paymentTypes' => collect(PaymentType::boxOffice())
                ->reject(fn (PaymentType $type) => $type === PaymentType::Transfer
                    && ! $request->user()->hasPrivilege((int) config('boxoffice.privileges.transfer_payment')))
                ->map(fn (PaymentType $type) => ['value' => $type->value, 'label' => $type->label()])
                ->values(),
            'note' => "Boleto a cambiar: folio {$old->pk_evento_funcion_boleto}, valor $".number_format($old->precio, 2),
        ]);
    }

    public function storeSelection(PickSeatsRequest $request, SeatSelectionResolver $resolver, int $evento): RedirectResponse
    {
        if (! $this->oldTicket()) {
            return redirect()->route('boxoffice.change.index');
        }

        $event = $this->event($evento);
        $functionId = (int) $request->validated('funcion');

        if (! $this->catalog->functionBelongsToEvent($functionId, $evento)) {
            throw ValidationException::withMessages(['funcion' => 'La función no pertenece a este evento.']);
        }

        ['locality' => $locality, 'section' => $section, 'seats' => $seats] = $resolver->resolve($event, $functionId, $request->validated());

        $lines = array_map(
            fn (string $seat, int $index) => new TicketLine(
                $locality->pk_evento_localidad,
                $locality->evento_localidad,
                $seat === 'NA_NA' ? 'NA_NA'.$index : $seat,
                $locality->precio,
                courtesy: true,
            ),
            $seats,
            array_keys($seats),
        );

        $this->store->put(new CheckoutState(
            $evento, $functionId, PaymentType::Courtesy, $lines,
            $locality->pk_evento_localidad, $section['sectionId'], $section['capacity'],
        ));
        $this->store->putValue(self::PAYMENT, (int) ($request->validated('tipo_pago') ?? PaymentType::Cash->value));

        return redirect()->route('boxoffice.change.summary', $evento);
    }

    public function summary(int $evento): Response|RedirectResponse
    {
        $old = $this->oldTicket();
        $event = $this->event($evento);
        $state = $this->store->get($evento);

        if (! $old || $state === null) {
            return redirect()->route('boxoffice.change.index');
        }

        if ($state->isPurchased()) {
            return redirect()->route('boxoffice.change.done', $evento);
        }

        $function = EventoFuncionVista::query()->findOrFail($state->functionId);
        $payment = PaymentType::from((int) $this->store->value(self::PAYMENT));
        $quote = $this->quote($event, $state, $old, $payment);

        return Inertia::render('BoxOffice/Change/Summary', [
            'event' => SeatPickerProps::event($event),
            'function' => ['date' => $function->fecha_natural, 'time' => $function->hora_natural],
            'oldTicket' => [
                'id' => $old->pk_evento_funcion_boleto,
                'seat' => $old->isGeneralAdmission() ? null : $old->asiento,
                'price' => $old->precio,
            ],
            'lines' => array_map(fn (TicketLine $line, int $index) => [
                'label' => $line->isGeneralAdmission() ? 'Boleto '.($index + 1) : $line->seat,
                'locality' => $line->localityName,
                'price' => $line->price,
            ], $state->lines, array_keys($state->lines)),
            'payment' => $payment->label(),
            'quote' => $quote,
            'confirmToken' => (string) Str::uuid(),
        ]);
    }

    public function confirm(ConfirmPurchaseRequest $request, PurchaseService $purchases, ConfirmationGuard $guard, int $evento): RedirectResponse
    {
        $old = $this->oldTicket();
        $event = $this->event($evento);
        $state = $this->store->get($evento);

        if (! $old || $state === null) {
            return redirect()->route('boxoffice.change.index');
        }

        try {
            $purchaseId = $guard->once(
                "{$request->user()->pk_usuario}:change:{$old->pk_evento_funcion_boleto}:{$request->validated('token')}",
                fn () => $this->exchange($purchases, $state, $event, $old, $request->user()),
            );
        } catch (SeatsUnavailableException $exception) {
            return redirect()->route('boxoffice.change.selection', $evento)->with('error', $exception->getMessage());
        }

        if ($purchaseId === ConfirmationGuard::IN_PROGRESS) {
            return back()->with('error', 'El cambio ya se está registrando. Espera un momento.');
        }

        $this->store->put($state->withPurchase($purchaseId));
        $this->store->forgetValue(self::OLD_TICKET);

        return redirect()->route('boxoffice.change.done', $evento);
    }

    public function done(int $evento): Response|RedirectResponse
    {
        $event = $this->event($evento);
        $state = $this->store->get($evento);

        if ($state === null || ! $state->isPurchased()) {
            return redirect()->route('boxoffice.change.index');
        }

        return Inertia::render('BoxOffice/Shared/Delivered', [
            'title' => 'Cambio registrado',
            'event' => SeatPickerProps::event($event),
            'purchaseId' => $state->purchaseId,
            'tickets' => count($state->lines),
            'printRoute' => 'boxoffice.change.print',
            'formats' => [],
            'defaultFormat' => null,
            'finishRoute' => 'boxoffice.change.finish',
        ]);
    }

    public function print(Request $request, TicketPrintService $printing, int $evento): View
    {
        $this->event($evento);
        $state = $this->store->get($evento);
        abort_unless($state?->isPurchased(), 404);

        return $printing->printView(EventosCompras::query()->findOrFail($state->purchaseId), $request->user(), TicketPresenter::BOX);
    }

    public function finish(int $evento): RedirectResponse
    {
        $this->store->forget($evento);

        return redirect()->route('boxoffice.change.index')->with('success', 'Cambio de boleto finalizado.');
    }

    private function exchange(PurchaseService $purchases, CheckoutState $state, EventoVista $event, EventosFuncionesBoletos $old, Usuarios $user): int
    {
        return DB::transaction(function () use ($purchases, $state, $event, $old, $user) {
            $locked = EventosFuncionesBoletos::query()->lockForUpdate()->findOrFail($old->pk_evento_funcion_boleto);

            if ($this->alreadyChanged($locked)) {
                throw new SeatsUnavailableException('Este boleto ya fue cambiado en otra taquilla.');
            }

            $purchase = $purchases->purchase($state, $event, $user);

            $locked->forceFill([
                'canjeable' => 0,
                'impresion' => 1,
                'pk_evento_funcion_boleto_status' => TicketStatus::Issued->value,
            ])->save();

            $purchase->boletos()->pluck('pk_evento_funcion_boleto')->each(fn (int $newTicketId) => (new BoletosCanjeados)->forceFill([
                'folio_boleto_anterior' => $locked->pk_evento_funcion_boleto,
                'folio_boleto_actual' => $newTicketId,
                'tipo_canje' => BoletosCanjeados::TYPE_BOX_OFFICE,
            ])->save());

            return $purchase->pk_evento_compra;
        });
    }

    /**
     * Cobro de la diferencia, con la fórmula del sistema legacy.
     */
    private function quote(EventoVista $event, CheckoutState $state, EventosFuncionesBoletos $old, PaymentType $payment): array
    {
        $locality = EventosLocalidades::query()->findOrFail($state->localityId);
        $rate = (float) (int) EventosComprasTipos::query()->whereKey($payment->value)->value('comision_porcentaje');
        $count = count($state->lines);
        $newTotal = array_sum(array_map(fn (TicketLine $line) => $line->price, $state->lines));
        $difference = $newTotal - $old->precio;

        [$service, $card] = match (CommissionType::tryFrom((int) $event->pk_evento_comision_tipo)) {
            CommissionType::Percentage => [$difference * ($locality->comision / 100), $difference * ($rate / 100)],
            CommissionType::Fixed => [$count * $locality->comision, $difference * ($rate / 100)],
            CommissionType::PercentageNoCardFee => [$difference * ($locality->comision / 100), 0.0],
            CommissionType::FixedNoCardFee => [$difference == 0.0 ? 0.0 : $count * $locality->comision, 0.0],
            default => [0.0, 0.0],
        };

        $seatsPerTable = (int) EventoLocalidadAsiento::query()->where('pk_evento_localidad', $locality->pk_evento_localidad)->value('cantidad_asientos');

        if ($seatsPerTable > 0) {
            $service *= $seatsPerTable;
        }

        return [
            'newTotal' => $newTotal,
            'difference' => $difference,
            'service' => $service,
            'card' => $card,
            'total' => round($difference + $service + $card, 2),
        ];
    }

    private function alreadyChanged(EventosFuncionesBoletos $ticket): bool
    {
        return (int) ($ticket->canjeable ?? 0) !== 0
            || BoletosCanjeados::query()->where('folio_boleto_anterior', $ticket->pk_evento_funcion_boleto)->exists();
    }

    private function oldTicket(): ?EventosFuncionesBoletos
    {
        $id = $this->store->value(self::OLD_TICKET);

        return $id ? EventosFuncionesBoletos::query()->find($id) : null;
    }

    private function event(int $eventId): EventoVista
    {
        Gate::authorize(BoxOfficeAbilities::EVENT, $eventId);

        return $this->catalog->event($eventId);
    }
}
