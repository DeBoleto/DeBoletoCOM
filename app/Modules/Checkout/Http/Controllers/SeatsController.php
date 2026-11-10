<?php

namespace App\Modules\Checkout\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BoxOffice\Services\Checkout\SeatAvailability;
use App\Modules\BoxOffice\Services\Checkout\TicketLine;
use App\Modules\Checkout\Exceptions\CheckoutException;
use App\Modules\Checkout\Http\CheckoutPresenter;
use App\Modules\Checkout\Http\Requests\HoldSeatRequest;
use App\Modules\Checkout\Http\Requests\StoreSelectionRequest;
use App\Modules\Checkout\Jobs\SendConversionEvent;
use App\Modules\Checkout\Services\SeatHolds;
use App\Modules\Checkout\Services\SelectionBuilder;
use App\Modules\Checkout\Services\WebCatalog;
use App\Modules\Checkout\Support\WebCart;
use App\Modules\Checkout\Support\WebCartStore;
use App\Modules\Events\Models\EventoReservaBoleto;
use App\Modules\Events\Models\EventosLocalidades;
use App\Modules\Events\Models\Views\EventoVista;
use App\Modules\Users\Models\Usuarios;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Paso de selección (mapa del recinto o cantidades por localidad). La
 * elección de función vive en la página del evento, así que la compra
 * empieza directamente aquí.
 */
class SeatsController extends Controller
{
    public function __construct(
        private readonly WebCatalog $catalog,
        private readonly WebCartStore $carts,
        private readonly SeatHolds $holds,
    ) {}

    public function show(Request $request, SeatAvailability $availability, string $slug): Response|RedirectResponse
    {
        $event = $this->catalog->eventBySlug($slug);
        $functions = $this->catalog->openFunctions($event->pk_evento);

        if ($functions->isEmpty()) {
            return redirect()->route('event.detail', $slug)->with('error', 'Este evento ya no tiene fechas a la venta.');
        }

        $requested = $request->integer('function');
        $function = $requested ? $functions->firstWhere('pk_evento_funcion', $requested) : ($functions->count() === 1 ? $functions->first() : null);

        if ($function === null) {
            return redirect()->route('event.detail', $slug)->with('error', 'Elige la fecha a la que quieres asistir.');
        }

        $localities = $this->catalog->openLocalities($event->pk_evento);
        $byQuantity = $event->sellsWithoutSeatMap();
        $eventRow = $this->catalog->eventRow($event->pk_evento);
        $cart = $this->carts->get($event->pk_evento);

        if ($cart === null) {
            SendConversionEvent::dispatch($event->pk_evento, 'InitiateCheckout', $request->fullUrl());
        }

        return Inertia::render('Checkout/Seats', [
            'event' => CheckoutPresenter::event($event),
            'function' => CheckoutPresenter::function($function),
            'mode' => $byQuantity ? 'quantities' : 'map',
            'stage' => $byQuantity ? null : $this->stage($event, $localities),
            'localities' => $byQuantity ? $this->quantityLocalities($event, $localities, $function->pk_evento_funcion, $availability) : [],
            'limits' => [
                'maxTickets' => $this->catalog->maxTickets($event->pk_evento),
                'holdMinutes' => (int) config('checkout.hold_minutes'),
            ],
            'parking' => $this->catalog->parkingOffer($eventRow),
            'cart' => $cart && $cart->selection->functionId === $function->pk_evento_funcion ? $this->previousSelection($cart) : null,
        ]);
    }

    /**
     * Estado de una sección del mapa: asientos vendidos, apartados por otras
     * personas, precio y reglas. El mapa lo consulta periódicamente.
     */
    public function section(Request $request, SeatAvailability $availability, int $evento, int $funcion, string $seccion): JsonResponse
    {
        $event = $this->catalog->event($evento);
        $this->catalog->openFunction($evento, $funcion);

        [$section, $locality] = $this->resolveSection($event, $seccion);

        $generalAdmission = $section['capacity'] !== null;
        $max = $this->catalog->maxTickets($evento);
        $owner = $this->carts->owner();

        $statuses = $generalAdmission ? [] : $this->catalog->seatStatuses($evento, $funcion, $locality->pk_evento_localidad);

        if (! $generalAdmission) {
            foreach ($this->holds->heldByOthers($evento, $funcion, $locality->evento_localidad, $owner) as $seat) {
                $statuses[$seat] ??= 'proceso';
            }

            foreach ($this->holds->heldBy($evento, $funcion, $locality->evento_localidad, $owner) as $seat) {
                $statuses[$seat] ??= 'mio';
            }
        }

        $volume = $this->catalog->volumeDiscount($locality);
        $extraChair = config('boxoffice.rules.extra_chair');

        return response()->json([
            'localityId' => $locality->pk_evento_localidad,
            'name' => $locality->evento_localidad,
            'price' => $this->catalog->listPrice($event, $locality),
            'capacity' => $section['capacity'],
            'remaining' => $generalAdmission
                ? min($max, $availability->remaining($evento, $funcion, $locality->pk_evento_localidad, $section['capacity']))
                : null,
            'statuses' => (object) $statuses,
            'specials' => $this->catalog->specials($event, $locality),
            'specialsOnEveryTicket' => $this->catalog->specialsOnEveryTicket($evento),
            'promotion' => $this->catalog->promotion($event, $locality),
            'volumeDiscount' => $volume ? ['from' => $volume->num_boleto, 'amount' => $volume->promocion] : null,
            'rules' => $this->catalog->rules($locality),
            'extraChair' => in_array($evento, $extraChair['events'], true) && in_array($locality->pk_evento_localidad, $extraChair['localities'], true)
                ? ['price' => $extraChair['price'] + $extraChair['commission']]
                : null,
        ]);
    }

    /**
     * Aparta o libera un asiento al tocarlo en el mapa.
     */
    public function hold(HoldSeatRequest $request, int $evento): JsonResponse
    {
        $event = $this->catalog->event($evento);
        $functionId = (int) $request->validated('funcion');
        $this->catalog->openFunction($evento, $functionId);

        [$section, $locality] = $this->resolveSection($event, $request->validated('seccion'));
        $seat = $request->validated('asiento');

        $valid = collect($section['rows'])->flatMap(fn (array $row) => $row['seats'])
            ->contains(fn (array $candidate) => $candidate['id'] === $seat && ($candidate['selectable'] ?? true));

        abort_unless($valid && $section['capacity'] === null, 422, 'Ese asiento no existe en la sección.');

        // Solo los clientes con sesión apartan en el servidor: así nadie puede
        // bloquear el inventario abriendo sesiones anónimas. Un invitado elige
        // en pantalla y aparta al continuar, ya con sesión.
        if (! Auth::guard('customer')->check()) {
            return response()->json(['status' => 'local', 'seat' => $seat]);
        }

        if ($request->validated('accion') === 'liberar') {
            $this->holds->release($evento, $functionId, $locality, $seat, $this->carts->owner());

            return response()->json(['status' => 'released', 'seat' => $seat]);
        }

        try {
            $this->holds->holdMany($evento, $functionId, $locality, [$seat], $this->carts->owner(), $this->catalog->maxTickets($evento));
        } catch (CheckoutException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['status' => 'held', 'seat' => $seat]);
    }

    public function store(StoreSelectionRequest $request, SelectionBuilder $builder, int $evento): RedirectResponse
    {
        $customer = Auth::guard('customer')->user();

        if (! $customer instanceof Usuarios) {
            return back()->with('auth_required', true);
        }

        $event = $this->catalog->event($evento);
        $functionId = (int) $request->validated('funcion');
        $this->catalog->openFunction($evento, $functionId);
        $owner = $this->carts->owner();

        try {
            $cart = $builder->build($event, $this->catalog->eventRow($evento), $functionId, $request->validated(), $customer, $owner);
        } catch (CheckoutException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $this->releaseOtherHolds($cart, $owner);
        $this->holds->refresh($owner);
        $this->carts->put($cart);

        SendConversionEvent::dispatch($evento, 'AddToCart', $request->headers->get('referer') ?? $request->fullUrl());

        return redirect()->route('checkout.payment', $evento);
    }

    public function cancel(int $evento): RedirectResponse
    {
        $event = EventoVista::query()->findOrFail($evento);

        $this->holds->releaseAll($this->carts->owner());
        $this->carts->forget($evento);

        return redirect()->route('event.detail', (string) $event->getAttribute('friendly_url'));
    }

    /**
     * @return array{0: array, 1: EventosLocalidades}
     */
    private function resolveSection(EventoVista $event, string $sectionId): array
    {
        $localities = $this->catalog->openLocalities($event->pk_evento);
        $section = $this->catalog->section($this->catalog->stage($event, $localities), $sectionId);
        $locality = $section ? $localities->firstWhere('pk_evento_localidad', (int) $section['localityId']) : null;

        abort_if($section === null || $locality === null, 404);

        return [$section, $locality];
    }

    /**
     * Mapa del recinto; las secciones de localidades cerradas quedan sin precio.
     */
    private function stage(EventoVista $event, Collection $localities): ?array
    {
        $stage = $this->catalog->stage($event, $localities);

        if ($stage === null) {
            return null;
        }

        $prices = $localities->mapWithKeys(fn (EventosLocalidades $locality) => [
            $locality->pk_evento_localidad => $this->catalog->listPrice($event, $locality),
        ]);

        $stage['sections'] = array_map(function (array $section) use ($prices) {
            $price = $prices->get((int) $section['localityId']);
            $section['available'] = $price !== null;
            $section['price'] = $price === null ? null : [
                'originalPrice' => $section['price']['originalPrice'] ?? $price,
                'discountPrice' => $price < ($section['price']['originalPrice'] ?? $price) ? $price : null,
            ];

            return $section;
        }, $stage['sections']);

        return $stage;
    }

    private function quantityLocalities(EventoVista $event, Collection $localities, int $functionId, SeatAvailability $availability): array
    {
        $max = $this->catalog->maxTickets($event->pk_evento);

        return $localities->map(fn (EventosLocalidades $locality) => [
            'id' => $locality->pk_evento_localidad,
            'name' => $locality->evento_localidad,
            'price' => $this->catalog->listPrice($event, $locality),
            'remaining' => min($max, $availability->remaining($event->pk_evento, $functionId, $locality->pk_evento_localidad, (int) $locality->capacidad)),
            'promotion' => $this->catalog->promotion($event, $locality),
            'rules' => $this->catalog->rules($locality),
        ])->values()->all();
    }

    private function previousSelection(WebCart $cart): array
    {
        $lines = collect($cart->selection->lines);

        return [
            'sectionId' => $cart->selection->sectionId,
            'seats' => $lines->reject(fn (TicketLine $line) => $line->isGeneralAdmission())->pluck('seat')->values()->all(),
            'quantity' => $lines->filter(fn (TicketLine $line) => $line->isGeneralAdmission())->count(),
            'quantities' => $lines->countBy('localityId')->all(),
            'parking' => $cart->parking,
        ];
    }

    private function releaseOtherHolds(WebCart $cart, string $owner): void
    {
        $keep = $cart->numberedSeats();

        EventoReservaBoleto::query()
            ->where('pk_usuario', $owner)
            ->where(fn ($query) => $query
                ->where('pk_evento_funcion', '!=', $cart->selection->functionId)
                ->orWhereNotIn('asiento', $keep === [] ? [''] : $keep))
            ->delete();
    }
}
