<?php

namespace App\Modules\Checkout\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BoxOffice\Services\Checkout\SeatAvailability;
use App\Modules\Checkout\Http\CheckoutPresenter;
use App\Modules\Checkout\Jobs\SendConversionEvent;
use App\Modules\Checkout\Services\SeatHolds;
use App\Modules\Checkout\Services\StageView;
use App\Modules\Checkout\Services\WebCatalog;
use App\Modules\Checkout\Support\WebCartStore;
use App\Modules\Events\Models\EventosLocalidades;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mapa completo del recinto (eventos/mapa/{evento}): todas las zonas en un
 * solo lienzo; al tocar una zona se acerca a sus asientos. Apartado, selección
 * y pago usan las mismas rutas que el proceso de compra.
 */
class StageMapController extends Controller
{
    public function __construct(
        private readonly WebCatalog $catalog,
        private readonly WebCartStore $carts,
        private readonly SeatHolds $holds,
        private readonly StageView $stages,
    ) {}

    public function show(Request $request, int $evento): Response|RedirectResponse
    {
        $event = $this->catalog->event($evento);
        $slug = (string) $event->getAttribute('friendly_url');
        $functions = $this->catalog->openFunctions($event->pk_evento);

        if ($functions->isEmpty()) {
            return redirect()->route('event.detail', $slug)->with('error', 'Este evento ya no tiene fechas a la venta.');
        }

        $requested = $request->integer('function');
        $function = $requested ? $functions->firstWhere('pk_evento_funcion', $requested) : ($functions->count() === 1 ? $functions->first() : null);

        if ($requested && $function === null) {
            return redirect()->route('checkout.map', $evento)->with('error', 'Esa fecha ya no está a la venta. Elige otra.');
        }

        $localities = $this->catalog->openLocalities($event->pk_evento);
        $stage = $event->sellsWithoutSeatMap() ? null : $this->stages->priced($event, $localities);

        if ($stage === null || ! $this->hasVenueLayout($stage)) {
            return redirect()->route('checkout.process', array_filter([
                'slug' => $slug,
                'function' => $function?->pk_evento_funcion,
            ]));
        }

        $props = [
            'event' => CheckoutPresenter::event($event),
            'functions' => $functions->map(fn ($candidate) => CheckoutPresenter::function($candidate))->values()->all(),
            'function' => null,
            'limits' => [
                'maxTickets' => $this->catalog->maxTickets($event->pk_evento),
                'holdMinutes' => (int) config('checkout.hold_minutes'),
            ],
        ];

        if ($function === null) {
            return Inertia::render('Checkout/StageMap', $props);
        }

        $cart = $this->carts->get($event->pk_evento);

        if ($cart === null) {
            SendConversionEvent::dispatch($event->pk_evento, 'InitiateCheckout', $request->fullUrl());
        }

        return Inertia::render('Checkout/StageMap', [
            ...$props,
            'function' => CheckoutPresenter::function($function),
            'stage' => $stage,
            'parking' => $this->catalog->parkingOffer($this->catalog->eventRow($event->pk_evento)),
            'cart' => $cart && $cart->selection->functionId === $function->pk_evento_funcion ? $this->stages->previousSelection($cart, $stage) : null,
        ]);
    }

    /**
     * Disponibilidad de todo el recinto: estado de cada asiento ocupado y
     * lugares libres por zona. El mapa completo la consulta periódicamente.
     */
    public function venue(SeatAvailability $availability, int $evento, int $funcion): JsonResponse
    {
        $event = $this->catalog->event($evento);
        $this->catalog->openFunction($evento, $funcion);

        $localities = $this->catalog->openLocalities($evento)->keyBy('pk_evento_localidad');
        $stage = $this->catalog->stage($event, $localities->values());
        $owner = $this->carts->owner();
        $max = $this->catalog->maxTickets($evento);
        $statusesByLocality = [];
        $zones = [];

        foreach ($stage['sections'] ?? [] as $section) {
            $locality = $localities->get((int) $section['localityId']);

            if (! $locality instanceof EventosLocalidades) {
                continue;
            }

            if ($section['capacity'] !== null) {
                $zones[$section['key']] = [
                    'remaining' => min($max, $availability->remaining($evento, $funcion, $locality->pk_evento_localidad, $section['capacity'])),
                    'statuses' => (object) [],
                ];

                continue;
            }

            $statusesByLocality[$locality->pk_evento_localidad] ??= $this->localityStatuses($evento, $funcion, $locality, $owner);
            $known = $statusesByLocality[$locality->pk_evento_localidad];

            $seats = collect($section['rows'])->flatMap(fn (array $row) => $row['seats']);
            $statuses = [];

            foreach ($seats as $seat) {
                if (isset($known[$seat['id']])) {
                    $statuses[$seat['id']] = $known[$seat['id']];
                }
            }

            $zones[$section['key']] = [
                'remaining' => $seats->filter(fn (array $seat) => ($seat['selectable'] ?? true)
                    && (! isset($statuses[$seat['id']]) || $statuses[$seat['id']] === 'mio'))->count(),
                'statuses' => (object) $statuses,
            ];
        }

        return response()->json(['zones' => (object) $zones]);
    }

    /**
     * El mapa completo coloca cada sección con su desplazamiento (num/numx).
     * Los mapas anteriores, sin desplazamientos, se encimarían: esos siguen
     * en el proceso de compra por zonas.
     */
    private function hasVenueLayout(array $stage): bool
    {
        $seated = array_filter($stage['sections'], fn (array $section) => $section['capacity'] === null && $section['rows'] !== []);

        if ($seated === []) {
            return false;
        }

        return count($seated) === 1
            || array_filter($seated, fn (array $section) => $section['offset']['x'] !== 0 || $section['offset']['y'] !== 0) !== [];
    }

    /**
     * @return array<string, string>
     */
    private function localityStatuses(int $evento, int $funcion, EventosLocalidades $locality, string $owner): array
    {
        $statuses = $this->catalog->seatStatuses($evento, $funcion, $locality->pk_evento_localidad);

        foreach ($this->holds->heldByOthers($evento, $funcion, $locality->evento_localidad, $owner) as $seat) {
            $statuses[$seat] ??= 'proceso';
        }

        foreach ($this->holds->heldBy($evento, $funcion, $locality->evento_localidad, $owner) as $seat) {
            $statuses[$seat] ??= 'mio';
        }

        return $statuses;
    }
}
