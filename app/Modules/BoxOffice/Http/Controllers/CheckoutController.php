<?php

namespace App\Modules\BoxOffice\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BoxOffice\Exceptions\SeatsUnavailableException;
use App\Modules\BoxOffice\Http\Requests\ConfirmPurchaseRequest;
use App\Modules\BoxOffice\Http\Requests\SelectTicketsRequest;
use App\Modules\BoxOffice\Policies\BoxOfficeAbilities;
use App\Modules\BoxOffice\Services\Checkout\CheckoutState;
use App\Modules\BoxOffice\Services\Checkout\CheckoutStore;
use App\Modules\BoxOffice\Services\Checkout\ConfirmationGuard;
use App\Modules\BoxOffice\Services\Checkout\PurchaseService;
use App\Modules\BoxOffice\Services\Checkout\SaleCatalog;
use App\Modules\BoxOffice\Services\Checkout\SeatSelectionResolver;
use App\Modules\BoxOffice\Services\Checkout\TicketLine;
use App\Modules\BoxOffice\Services\Checkout\TicketPricing;
use App\Modules\BoxOffice\Support\EventImage;
use App\Modules\Events\Models\EventoReservaBoleto;
use App\Modules\Events\Models\Eventos;
use App\Modules\Events\Models\EventosLocalidades;
use App\Modules\Events\Models\EventosLocalidadesEspeciales;
use App\Modules\Events\Models\Views\EventoFuncionVista;
use App\Modules\Events\Models\Views\EventoVista;
use App\Modules\Orders\Enums\PaymentType;
use App\Modules\Users\Models\Usuarios;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Asistente de venta de taquilla: selección → resumen → venta registrada.
 */
class CheckoutController extends Controller
{
    public function __construct(
        private readonly SaleCatalog $catalog,
        private readonly CheckoutStore $store,
    ) {}

    public function start(int $evento): RedirectResponse
    {
        $this->sellableEvent($evento);
        $this->store->forget($evento);
        EventoReservaBoleto::releaseFor($this->store->owner());

        return redirect()->route('boxoffice.tickets.checkout.selection', $evento);
    }

    public function selection(Request $request, int $evento): Response
    {
        $event = $this->sellableEvent($evento);
        $localities = $this->catalog->localities($evento);
        $byQuantity = $event->sellsWithoutSeatMap();
        $state = $this->store->get($evento);

        return Inertia::render('BoxOffice/Tickets/Checkout/Selection', [
            'event' => $this->presentEvent($event) + ['mode' => $byQuantity ? 'quantities' : 'map'],
            'functions' => $this->catalog->functions($evento)->map(fn (EventoFuncionVista $function) => [
                'id' => $function->pk_evento_funcion,
                'date' => $function->fecha_natural,
                'time' => $function->hora_natural,
            ])->values(),
            'localities' => $localities->map(fn (EventosLocalidades $locality) => [
                'id' => $locality->pk_evento_localidad,
                'name' => $locality->evento_localidad,
                'price' => $locality->precio,
                'capacity' => $locality->capacidad,
                'specials' => $locality->especiales->map(fn (EventosLocalidadesEspeciales $special) => [
                    'id' => $special->pk_evento_localidad_especiales,
                    'name' => $special->evento_localidad,
                    'price' => $special->precio,
                ])->values(),
            ])->values(),
            'stage' => $byQuantity ? null : $this->catalog->stage($event, $localities),
            'paymentTypes' => $this->paymentTypes($request->user()),
            'discounts' => config('boxoffice.allowed_discounts'),
            'extraChair' => $this->extraChair($event),
            'previous' => $state && ! $state->isPurchased() ? [
                'functionId' => $state->functionId,
                'paymentType' => $state->paymentType->value,
                'discount' => $state->discount,
            ] : null,
        ]);
    }

    public function storeSelection(SelectTicketsRequest $request, TicketPricing $pricing, SeatSelectionResolver $seats, int $evento): RedirectResponse
    {
        $event = $this->sellableEvent($evento);
        $functionId = (int) $request->validated('funcion');

        if (! $this->catalog->functionBelongsToEvent($functionId, $evento)) {
            throw ValidationException::withMessages(['funcion' => 'La función no pertenece a este evento.']);
        }

        EventoReservaBoleto::releaseFor($this->store->owner());

        $state = $request->sellsByQuantity()
            ? $this->quantitiesState($request, $pricing, $event, $functionId)
            : $this->seatMapState($request, $pricing, $seats, $event, $functionId);

        $this->store->put($state);

        return redirect()->route('boxoffice.tickets.checkout.summary', $evento);
    }

    public function summary(int $evento): Response|RedirectResponse
    {
        $event = $this->sellableEvent($evento);
        $state = $this->store->get($evento);

        if ($state === null) {
            return redirect()->route('boxoffice.tickets.checkout.selection', $evento);
        }

        if ($state->isPurchased()) {
            return redirect()->route('boxoffice.tickets.checkout.complete', $evento);
        }

        $function = EventoFuncionVista::query()->findOrFail($state->functionId);

        return Inertia::render('BoxOffice/Tickets/Checkout/Summary', [
            'event' => $this->presentEvent($event) + ['comments' => $event->comentarios],
            'function' => ['date' => $function->fecha_natural, 'time' => $function->hora_natural],
            'paymentType' => ['value' => $state->paymentType->value, 'label' => $state->paymentType->label()],
            'promotionText' => $state->promotionText,
            'discount' => $state->discount,
            'confirmToken' => (string) Str::uuid(),
            'lines' => array_map(fn (TicketLine $line, int $index) => [
                'label' => $line->isGeneralAdmission() ? 'Boleto '.($index + 1) : $line->seat,
                'locality' => $line->localityName,
                'type' => $line->specialLocalityName,
                'price' => $line->price,
                'courtesy' => $line->courtesy,
            ], $state->lines, array_keys($state->lines)),
            'totals' => [
                'subtotal' => $state->subtotal(),
                'service' => $state->serviceFees(),
                'payment' => $state->paymentFees(),
                'total' => $state->total(),
                'paid' => $state->paidCount(),
                'courtesies' => $state->courtesyCount(),
            ],
        ]);
    }

    public function confirm(ConfirmPurchaseRequest $request, PurchaseService $purchases, ConfirmationGuard $guard, int $evento): RedirectResponse
    {
        $event = $this->sellableEvent($evento);
        $state = $this->store->get($evento);

        if ($state === null) {
            return redirect()->route('boxoffice.tickets.checkout.selection', $evento);
        }

        if ($state->isPurchased()) {
            return redirect()->route('boxoffice.tickets.checkout.complete', $evento);
        }

        try {
            $purchaseId = $guard->once(
                "{$request->user()->pk_usuario}:sale:{$evento}:{$request->validated('token')}",
                fn () => $purchases->purchase($state, $event, $request->user())->pk_evento_compra,
            );
        } catch (SeatsUnavailableException $exception) {
            return redirect()
                ->route('boxoffice.tickets.checkout.selection', $evento)
                ->with('error', $exception->getMessage());
        }

        if ($purchaseId === ConfirmationGuard::IN_PROGRESS) {
            return back()->with('error', 'La venta ya se está registrando. Espera un momento.');
        }

        $this->store->put($state->withPurchase($purchaseId));

        return redirect()->route('boxoffice.tickets.checkout.complete', $evento);
    }

    public function complete(int $evento): Response|RedirectResponse
    {
        $event = $this->sellableEvent($evento);
        $state = $this->store->get($evento);

        if ($state === null || ! $state->isPurchased()) {
            return redirect()->route('boxoffice.tickets.checkout.selection', $evento);
        }

        return Inertia::render('BoxOffice/Tickets/Checkout/Complete', [
            'event' => $this->presentEvent($event),
            'purchaseId' => $state->purchaseId,
            'isBond' => (int) $event->bono === 1,
            'total' => $state->total(),
            'tickets' => count($state->lines),
        ]);
    }

    public function finish(Request $request, int $evento): RedirectResponse
    {
        $this->sellableEvent($evento);
        $this->store->forget($evento);
        EventoReservaBoleto::releaseFor($this->store->owner());

        return $request->boolean('otra_venta')
            ? redirect()->route('boxoffice.tickets.checkout.start', $evento)
            : redirect()->route('boxoffice.tickets.index')->with('success', 'Venta finalizada.');
    }

    public function cancel(int $evento): RedirectResponse
    {
        $this->store->forget($evento);
        EventoReservaBoleto::releaseFor($this->store->owner());

        return redirect()->route('boxoffice.tickets.index');
    }

    private function seatMapState(SelectTicketsRequest $request, TicketPricing $pricing, SeatSelectionResolver $resolver, EventoVista $event, int $functionId): CheckoutState
    {
        ['locality' => $locality, 'section' => $section, 'seats' => $seats] = $resolver->resolve($event, $functionId, $request->validated());

        $priced = $pricing->forSeatMap(
            $event,
            $locality,
            $request->paymentType(),
            $seats,
            $request->boolean('promocion'),
            $request->discount(),
            array_map('intval', (array) $request->validated('especiales', [])),
            (array) $request->validated('sillas_extra', []),
        );

        return new CheckoutState(
            $event->pk_evento, $functionId, $request->paymentType(), $priced->lines,
            $locality->pk_evento_localidad, $section['sectionId'], $section['capacity'],
            $priced->promotion, $request->discount(), $priced->promotionText,
        );
    }

    private function quantitiesState(SelectTicketsRequest $request, TicketPricing $pricing, EventoVista $event, int $functionId): CheckoutState
    {
        $localities = $this->catalog->localities($event->pk_evento);
        $quantities = collect((array) $request->validated('cantidades'))
            ->mapWithKeys(fn ($quantity, $localityId) => [(int) $localityId => (int) $quantity])
            ->only($localities->pluck('pk_evento_localidad')->all())
            ->all();

        $priced = $pricing->forQuantities(
            $event, $localities, $request->paymentType(), $quantities,
            $request->boolean('promocion'), $request->discount(),
        );

        if ($priced->lines === []) {
            throw ValidationException::withMessages(['cantidades' => 'Indica al menos un boleto.']);
        }

        return new CheckoutState(
            $event->pk_evento, $functionId, $request->paymentType(), $priced->lines,
            promotion: $priced->promotion, discount: $request->discount(), promotionText: $priced->promotionText,
        );
    }

    private function sellableEvent(int $eventId): EventoVista
    {
        Gate::authorize(BoxOfficeAbilities::EVENT, $eventId);

        $event = $this->catalog->event($eventId);
        abort_unless(in_array($event->pk_evento_status, [Eventos::STATUS_DRAFT, Eventos::STATUS_PUBLISHED], true), 404);

        return $event;
    }

    private function presentEvent(EventoVista $event): array
    {
        return [
            'id' => $event->pk_evento,
            'name' => $event->evento,
            'image' => EventImage::url($event->imagen),
            'venue' => $event->direccionVisible(),
            'city' => $event->ciudad,
            'state' => $event->estado,
        ];
    }

    private function paymentTypes(Usuarios $user): array
    {
        $canTransfer = $user->hasPrivilege((int) config('boxoffice.privileges.transfer_payment'));

        return collect(PaymentType::boxOffice())
            ->reject(fn (PaymentType $type) => $type === PaymentType::Transfer && ! $canTransfer)
            ->map(fn (PaymentType $type) => ['value' => $type->value, 'label' => $type->label()])
            ->values()
            ->all();
    }

    private function extraChair(EventoVista $event): ?array
    {
        $rule = config('boxoffice.rules.extra_chair');

        if (! in_array($event->pk_evento, $rule['events'], true)) {
            return null;
        }

        return [
            'localities' => $rule['localities'],
            'price' => $rule['price'],
            'commission' => $rule['commission'],
        ];
    }
}
