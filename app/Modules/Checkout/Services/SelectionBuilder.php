<?php

namespace App\Modules\Checkout\Services;

use App\Modules\BoxOffice\Services\Checkout\CheckoutState;
use App\Modules\BoxOffice\Services\Checkout\SeatAvailability;
use App\Modules\BoxOffice\Services\Checkout\SeatSelectionResolver;
use App\Modules\BoxOffice\Services\Checkout\TicketLine;
use App\Modules\BoxOffice\Services\Checkout\TicketPricing;
use App\Modules\Checkout\Exceptions\CheckoutException;
use App\Modules\Checkout\Support\WebCart;
use App\Modules\Events\Models\Eventos;
use App\Modules\Events\Models\EventosLocalidades;
use App\Modules\Events\Models\Views\EventoVista;
use App\Modules\Orders\Enums\PaymentType;
use App\Modules\Tickets\Models\EventosFuncionesBoletos;
use App\Modules\Users\Models\Usuarios;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Convierte lo elegido en el mapa (o las cantidades por localidad) en un
 * carrito con precios calculados en el servidor y asientos apartados.
 */
class SelectionBuilder
{
    public function __construct(
        private readonly WebCatalog $catalog,
        private readonly SeatSelectionResolver $resolver,
        private readonly TicketPricing $pricing,
        private readonly SeatAvailability $availability,
        private readonly SeatHolds $holds,
    ) {}

    /**
     * @param  array<string, mixed>  $input  Datos validados de StoreSelectionRequest.
     *
     * @throws ValidationException|CheckoutException
     */
    public function build(EventoVista $event, Eventos $row, int $functionId, array $input, Usuarios $customer, string $owner): WebCart
    {
        $this->ensureCanPurchase($event, $customer);

        $selection = $event->sellsWithoutSeatMap()
            ? $this->fromQuantities($event, $functionId, (array) ($input['cantidades'] ?? []))
            : $this->fromSeatMap($event, $functionId, $input, $owner);

        $parking = (bool) ($input['estacionamiento'] ?? false) && $this->catalog->parkingOffer($row) !== null;

        return new WebCart($selection, $parking, now()->addMinutes((int) config('checkout.hold_minutes'))->timestamp);
    }

    /**
     * Eventos que solo permiten una compra por cliente.
     */
    public function ensureCanPurchase(EventoVista $event, Usuarios $customer): void
    {
        if (! in_array($event->pk_evento, config('checkout.single_purchase_events'), true)) {
            return;
        }

        $alreadyBought = EventosFuncionesBoletos::query()
            ->where('pk_evento', $event->pk_evento)
            ->where('adquisicion_pk_usuario', $customer->pk_usuario)
            ->exists();

        if ($alreadyBought) {
            throw new CheckoutException('Este evento permite una sola compra por persona y tu cuenta ya tiene boletos.');
        }
    }

    /**
     * Selección del mapa: una o varias secciones, cada una con asientos
     * numerados o cantidad de cupo general. Se aparta todo o nada.
     */
    private function fromSeatMap(EventoVista $event, int $functionId, array $input, string $owner): CheckoutState
    {
        $requested = $this->requestedSections($input);
        $max = $this->catalog->maxTickets($event->pk_evento);
        $openIds = $this->catalog->openLocalities($event->pk_evento)->pluck('pk_evento_localidad')->all();

        $resolved = [];

        foreach ($requested as $section) {
            if (! in_array((int) ($section['localidad'] ?? 0), $openIds, true)) {
                throw ValidationException::withMessages(['seccion' => 'Esa zona no está a la venta.']);
            }

            $resolved[] = $this->resolver->resolve($event, $functionId, $section) + ['input' => $section];
        }

        if (array_sum(array_map(fn (array $section) => count($section['seats']), $resolved)) > $max) {
            throw ValidationException::withMessages(['asientos' => "Puedes comprar hasta {$max} boletos por compra."]);
        }

        $numbered = array_filter($resolved, fn (array $section) => $section['section']['capacity'] === null);

        $this->holds->releaseExcept($event->pk_evento, $functionId, $owner, collect($numbered)
            ->groupBy(fn (array $section) => $section['locality']->evento_localidad)
            ->map(fn ($sections) => $sections->flatMap(fn (array $section) => $section['seats'])->values()->all())
            ->all());

        DB::transaction(function () use ($numbered, $event, $functionId, $owner, $max) {
            foreach ($numbered as $section) {
                $this->holds->holdMany($event->pk_evento, $functionId, $section['locality'], $section['seats'], $owner, $max);
            }
        });

        $lines = [];
        $promotion = 0;
        $promotionTexts = [];
        $gaIndex = [];

        foreach ($resolved as ['locality' => $locality, 'section' => $section, 'seats' => $seats, 'input' => $sectionInput]) {
            $specials = $this->specials($event, $seats, (array) ($sectionInput['especiales'] ?? []));
            $extraChairs = array_values(array_intersect((array) ($sectionInput['sillas_extra'] ?? []), $seats));

            $priced = $this->pricing->forSeatMap(
                $event,
                $locality,
                PaymentType::Web,
                $seats,
                false,
                null,
                $specials,
                $extraChairs,
                $this->volumeDiscount($event, $locality, count($seats)),
            );

            foreach ($priced->lines as $line) {
                if ($line->isGeneralAdmission()) {
                    $index = $gaIndex[$locality->pk_evento_localidad] = ($gaIndex[$locality->pk_evento_localidad] ?? -1) + 1;
                    $line = new TicketLine(
                        $line->localityId, $line->localityName, 'NA_NA'.$index, $line->price, $line->serviceFee,
                        $line->paymentFee, $line->courtesy, $line->specialLocalityId, $line->specialLocalityName,
                    );
                }

                $lines[] = $line;
            }

            $promotion = max($promotion, $priced->promotion);

            if ($priced->promotionText !== '') {
                $promotionTexts[] = $priced->promotionText;
            }
        }

        $promotionText = implode(', ', array_unique($promotionTexts));

        if (count($resolved) === 1) {
            $section = $resolved[0]['section'];

            return new CheckoutState(
                $event->pk_evento, $functionId, PaymentType::Web, $lines,
                $resolved[0]['locality']->pk_evento_localidad, $section['sectionId'], $section['capacity'],
                $promotion, null, $promotionText,
            );
        }

        $capacities = [];

        foreach ($resolved as ['locality' => $locality, 'section' => $section]) {
            $id = $locality->pk_evento_localidad;
            $capacities[$id] = $section['capacity'] === null || (array_key_exists($id, $capacities) && $capacities[$id] === null)
                ? null
                : ($capacities[$id] ?? 0) + $section['capacity'];
        }

        return new CheckoutState(
            $event->pk_evento, $functionId, PaymentType::Web, $lines,
            promotion: $promotion, promotionText: $promotionText, capacities: $capacities,
        );
    }

    /**
     * Normaliza la selección: `secciones` (varias zonas) o los campos de una
     * sola sección. Una sección no puede repetirse.
     *
     * @return list<array<string, mixed>>
     */
    private function requestedSections(array $input): array
    {
        $sections = isset($input['secciones'])
            ? array_values((array) $input['secciones'])
            : [array_intersect_key($input, array_flip(['localidad', 'seccion', 'asientos', 'cantidad', 'especiales', 'sillas_extra']))];

        if ($sections === [] || count(array_unique(array_map(fn (array $section) => (string) ($section['seccion'] ?? ''), $sections))) !== count($sections)) {
            throw ValidationException::withMessages(['secciones' => 'Revisa las zonas de tu selección.']);
        }

        return $sections;
    }

    private function fromQuantities(EventoVista $event, int $functionId, array $input): CheckoutState
    {
        $localities = $this->catalog->openLocalities($event->pk_evento);
        $quantities = collect($input)
            ->mapWithKeys(fn ($quantity, $localityId) => [(int) $localityId => max(0, (int) $quantity)])
            ->only($localities->pluck('pk_evento_localidad')->all())
            ->filter();

        $max = $this->catalog->maxTickets($event->pk_evento);

        if ($quantities->isEmpty()) {
            throw ValidationException::withMessages(['cantidades' => 'Elige al menos un boleto.']);
        }

        if ($quantities->sum() > $max) {
            throw ValidationException::withMessages(['cantidades' => "Puedes comprar hasta {$max} boletos por compra."]);
        }

        foreach ($quantities as $localityId => $quantity) {
            $locality = $localities->firstWhere('pk_evento_localidad', $localityId);
            $remaining = $this->availability->remaining($event->pk_evento, $functionId, $localityId, (int) $locality->capacidad);

            if ($quantity > $remaining) {
                throw ValidationException::withMessages([
                    'cantidades' => "Solo quedan {$remaining} boletos en {$locality->evento_localidad}.",
                ]);
            }
        }

        $priced = $this->pricing->forQuantities($event, $localities, PaymentType::Web, $quantities->all(), false, null);

        return new CheckoutState(
            $event->pk_evento, $functionId, PaymentType::Web, $priced->lines,
            promotion: $priced->promotion, promotionText: $priced->promotionText,
        );
    }

    /**
     * Valida en qué boletos se pidió un tipo especial. En legacy solo se ofrece
     * en cada segundo boleto, salvo en los eventos configurados.
     *
     * @param  list<string>  $seats
     * @param  array<string, mixed>  $requested  asiento (o "BoletoN") => id de localidad especial.
     * @return array<string, int>
     */
    private function specials(EventoVista $event, array $seats, array $requested): array
    {
        $requested = array_filter($requested, fn ($id) => (int) $id > 0);

        if ($requested === []) {
            return [];
        }

        if (in_array($event->pk_evento, config('checkout.specials.disabled_events'), true)) {
            throw ValidationException::withMessages(['especiales' => 'Este evento no tiene tipos de boleto especiales en línea.']);
        }

        $everyTicket = $this->catalog->specialsOnEveryTicket($event->pk_evento);
        $allowed = [];

        foreach ($seats as $index => $seat) {
            if ($everyTicket || $index % 2 === 1) {
                $allowed[] = $seat === 'NA_NA' ? 'Boleto'.($index + 1) : $seat;
            }
        }

        if (array_diff(array_keys($requested), $allowed) !== []) {
            throw ValidationException::withMessages(['especiales' => 'El tipo de boleto especial no está disponible para ese boleto.']);
        }

        return array_map('intval', $requested);
    }

    /**
     * Descuento por volumen de la localidad (eventos_localidades_promos).
     */
    private function volumeDiscount(EventoVista $event, EventosLocalidades $locality, int $tickets): float
    {
        $promo = $this->catalog->volumeDiscount($locality);

        if ($promo === null) {
            return 0.0;
        }

        $paidTickets = $tickets - $this->pricing->promotionFor($event, $locality);

        return $paidTickets >= $promo->num_boleto ? (float) $promo->promocion : 0.0;
    }
}
