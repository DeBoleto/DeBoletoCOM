<?php

namespace App\Modules\Checkout\Services;

use App\Modules\BoxOffice\Services\Checkout\CheckoutState;
use App\Modules\BoxOffice\Services\Checkout\SeatAvailability;
use App\Modules\BoxOffice\Services\Checkout\SeatSelectionResolver;
use App\Modules\BoxOffice\Services\Checkout\TicketPricing;
use App\Modules\Checkout\Exceptions\CheckoutException;
use App\Modules\Checkout\Support\WebCart;
use App\Modules\Events\Models\Eventos;
use App\Modules\Events\Models\EventosLocalidades;
use App\Modules\Events\Models\Views\EventoVista;
use App\Modules\Orders\Enums\PaymentType;
use App\Modules\Tickets\Models\EventosFuncionesBoletos;
use App\Modules\Users\Models\Usuarios;
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

    private function fromSeatMap(EventoVista $event, int $functionId, array $input, string $owner): CheckoutState
    {
        $openIds = $this->catalog->openLocalities($event->pk_evento)->pluck('pk_evento_localidad')->all();

        if (! in_array((int) ($input['localidad'] ?? 0), $openIds, true)) {
            throw ValidationException::withMessages(['seccion' => 'Esa zona no está a la venta.']);
        }

        ['locality' => $locality, 'section' => $section, 'seats' => $seats] = $this->resolver->resolve($event, $functionId, $input);

        $max = $this->catalog->maxTickets($event->pk_evento);

        if (count($seats) > $max) {
            throw ValidationException::withMessages(['asientos' => "Puedes comprar hasta {$max} boletos por compra."]);
        }

        if ($section['capacity'] === null) {
            $this->holds->holdMany($event->pk_evento, $functionId, $locality, $seats, $owner, $max);
        }

        $specials = $this->specials($event, $seats, (array) ($input['especiales'] ?? []));
        $extraChairs = array_values(array_intersect((array) ($input['sillas_extra'] ?? []), $seats));

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

        return new CheckoutState(
            $event->pk_evento, $functionId, PaymentType::Web, $priced->lines,
            $locality->pk_evento_localidad, $section['sectionId'], $section['capacity'],
            $priced->promotion, null, $priced->promotionText,
        );
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
