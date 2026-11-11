<?php

namespace App\Modules\Checkout\Services;

use App\Modules\BoxOffice\Exceptions\SeatsUnavailableException;
use App\Modules\BoxOffice\Services\Checkout\SeatAvailability;
use App\Modules\BoxOffice\Services\Checkout\TicketIssuer;
use App\Modules\BoxOffice\Services\Checkout\TicketLine;
use App\Modules\Checkout\Exceptions\CheckoutException;
use App\Modules\Checkout\Support\WebCart;
use App\Modules\Events\Models\Eventos;
use App\Modules\Events\Models\EventosLocalidades;
use App\Modules\Events\Models\Views\EventoVista;
use App\Modules\Orders\Enums\PaymentType;
use App\Modules\Orders\Enums\PurchaseStatus;
use App\Modules\Orders\Models\EventosCompraLog;
use App\Modules\Orders\Models\EventosCompras;
use App\Modules\Orders\Models\EventosComprasTipos;
use App\Modules\Tickets\Models\EventoEstacionamientoBoleto;
use App\Modules\Users\Models\Usuarios;
use Illuminate\Support\Facades\DB;

/**
 * Registra la compra web ANTES de cobrar: orden en espera de pago y boletos
 * pendientes (estatus 4), de modo que nadie más pueda tomar esos asientos
 * mientras el cliente se autentica con su banco. Si el cobro no se completa,
 * PurchaseFinalizer libera todo.
 */
class PendingPurchaseWriter
{
    public const SOURCE = 'web-v2';

    public function __construct(
        private readonly SeatAvailability $availability,
        private readonly SeatHolds $holds,
        private readonly TicketIssuer $issuer,
        private readonly WebCatalog $catalog,
        private readonly SelectionBuilder $selections,
        private readonly QuoteCalculator $quotes,
    ) {}

    /**
     * @param  'card'|'transfer'  $method
     *
     * @throws SeatsUnavailableException|CheckoutException
     */
    public function create(WebCart $cart, Quote $quote, EventoVista $event, Usuarios $customer, string $method, string $owner, ?string $whatsapp): PendingPurchase
    {
        return DB::transaction(function () use ($cart, $quote, $event, $customer, $method, $owner, $whatsapp) {
            $state = $cart->selection;
            $localityIds = collect($state->lines)->pluck('localityId')->unique()->values();

            $localities = EventosLocalidades::query()
                ->where('pk_evento', $event->pk_evento)
                ->whereIn('pk_evento_localidad', $localityIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('pk_evento_localidad');

            if ($localities->count() !== $localityIds->count() || ! $this->availability->isAvailable($state) || $this->heldByOthers($cart, $localities, $owner)) {
                throw new SeatsUnavailableException;
            }

            $this->selections->ensureCanPurchase($event, $customer);

            $eventRow = Eventos::query()->whereKey($event->pk_evento)->lockForUpdate()->firstOrFail();

            if ($cart->parking && $this->catalog->parkingOffer($eventRow) === null) {
                throw new CheckoutException('Ya no hay lugares disponibles de '.mb_strtolower(config('checkout.parking_labels')[$event->pk_evento] ?? config('checkout.parking_label')).'.');
            }

            $salesPeriodId = $this->issuer->salesPeriodId($event->pk_evento);
            $status = $method === 'transfer' ? PurchaseStatus::AwaitingTransfer : PurchaseStatus::AwaitingPayment;

            $purchase = $this->issuer->newPurchase($customer, $event->pk_evento, $state->functionId, PaymentType::Web, [
                'estatus' => $status->value,
            ]);

            ['lines' => $lines, 'markers' => $markers] = $this->issuer->expandTables($state, $event, $customer, $salesPeriodId);
            $lines = $this->applyCouponToPrices($lines, $quote);

            foreach ($lines as $line) {
                $this->issuer->issue($purchase, $state, $line, $salesPeriodId, $customer);
            }

            if ($quote->parking !== null) {
                EventoEstacionamientoBoleto::query()->create([
                    'pk_evento_compra' => $purchase->pk_evento_compra,
                    'pk_evento' => $event->pk_evento,
                    'lugar' => EventoEstacionamientoBoleto::UNASSIGNED,
                    'precio' => $quote->parking['price'],
                ]);
            }

            $this->applyTotals($purchase, $lines, $quote, $event);

            $log = EventosCompraLog::query()->create([
                'pk_usuario' => $customer->pk_usuario,
                'json_compra' => json_encode([
                    'source' => self::SOURCE,
                    'purchase_id' => $purchase->pk_evento_compra,
                    'purchase_code' => $purchase->codigo,
                    'owner' => $owner,
                    'method' => $method,
                    'amount' => $quote->total(),
                    'markers' => $markers,
                    'whatsapp' => $whatsapp,
                    'cart' => $cart->toArray(),
                    'quote' => $quote->toArray(),
                ], JSON_UNESCAPED_UNICODE),
                'msi' => $quote->installments,
                'type' => $method === 'transfer' ? 2 : 1,
                'promo_id' => $quote->coupon?->id,
                'promo_total' => $quote->discount,
            ]);

            return new PendingPurchase($purchase, $log, $quote->total());
        });
    }

    /**
     * Asientos numerados del carrito que otra sesión tiene apartados.
     */
    private function heldByOthers(WebCart $cart, $localities, string $owner): bool
    {
        $state = $cart->selection;

        foreach ($cart->numberedSeatsByLocality() as $localityId => $seats) {
            $locality = $localities->get($localityId);
            $others = $this->holds->heldByOthers($state->eventId, $state->functionId, $locality->evento_localidad, $owner);

            if (array_intersect($seats, $others) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * El descuento del código (salvo "N+1 x N") se reparte entre los boletos pagados.
     *
     * @param  list<TicketLine>  $lines
     * @return list<TicketLine>
     */
    private function applyCouponToPrices(array $lines, Quote $quote): array
    {
        if ($quote->coupon === null || ! $quote->coupon->spreadsOverTickets() || $quote->discount <= 0) {
            return $lines;
        }

        $coupon = $quote->coupon;
        $discounted = fn (TicketLine $line) => ! $line->courtesy && $coupon->covers($line);
        $paid = count(array_filter($lines, $discounted));
        $perTicket = $paid > 0 ? $quote->discount / $paid : 0.0;

        return array_map(fn (TicketLine $line) => $discounted($line)
            ? $line->withPrice(max(0.0, $line->price - $perTicket))
            : $line, $lines);
    }

    /**
     * Totales de eventos_compras para una venta en línea (port corregido de
     * EventosCompras::update de legacy, que sumaba la comisión por cada boleto).
     *
     * @param  list<TicketLine>  $lines
     */
    private function applyTotals(EventosCompras $purchase, array $lines, Quote $quote, EventoVista $event): void
    {
        $total = array_sum(array_map(fn (TicketLine $line) => $line->courtesy ? 0 : $line->price, $lines));
        $appCommission = $quote->serviceFee;
        $onlineCommission = 0.0;

        if (! in_array($event->pk_evento, config('boxoffice.rules.card_fee_exempt_events'), true)) {
            $onlineCommission = $quote->installments !== null
                ? ($total + $appCommission) * ($this->quotes->installmentRate($quote->installments) / 100) + (float) config('checkout.installments.fixed_fee')
                : ($total + $appCommission) * ((float) EventosComprasTipos::query()->whereKey(PaymentType::Web->value)->value('comision_porcentaje') / 100) + (float) config('boxoffice.gateway_fixed_fee');
        }

        $purchase->forceFill([
            'total_compra' => $total,
            'total_comision_aplicacion' => $appCommission,
            'total_comision_tarjeta' => 0,
            'total_comision_pago_linea' => $onlineCommission,
            'total_utilidad' => $total - $appCommission - $onlineCommission,
            'total_envio' => $quote->deliveryFee,
            'total_descuento' => $quote->discount,
            'total_defender' => $quote->defender,
            'pk_evento_codigo' => $quote->coupon?->id,
        ])->save();
    }
}
