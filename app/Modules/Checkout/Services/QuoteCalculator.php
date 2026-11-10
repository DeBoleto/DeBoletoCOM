<?php

namespace App\Modules\Checkout\Services;

use App\Modules\BoxOffice\Services\Checkout\TicketLine;
use App\Modules\Checkout\Exceptions\CheckoutException;
use App\Modules\Checkout\Support\WebCart;
use App\Modules\Events\Enums\CommissionType;
use App\Modules\Events\Models\Eventos;
use App\Modules\Events\Models\EventosLocalidades;
use App\Modules\Orders\Enums\PaymentType;
use App\Modules\Orders\Models\EventosComprasTipos;
use Illuminate\Support\Collection;

/**
 * Calcula el cobro web: boletos, cargos, estacionamiento, envío, Ticket
 * Defender, meses sin intereses y código promocional.
 */
class QuoteCalculator
{
    public function __construct(
        private readonly WebCatalog $catalog,
        private readonly CouponResolver $coupons,
    ) {}

    /**
     * @throws CheckoutException cuando una opción elegida no aplica.
     */
    public function quote(WebCart $cart, Eventos $event, QuoteOptions $options = new QuoteOptions): Quote
    {
        $lines = $cart->selection->lines;
        $paid = array_values(array_filter($lines, fn (TicketLine $line) => $line->chargesFees()));
        $localities = $this->localities($lines);

        $subtotal = array_sum(array_map(fn (TicketLine $line) => $line->courtesy ? 0 : $line->price, $lines));
        $serviceFee = array_sum(array_map(fn (TicketLine $line) => $line->serviceFee, $paid));
        $paymentFee = array_sum(array_map(fn (TicketLine $line) => $line->paymentFee, $paid));

        $parking = $cart->parking ? $this->parking($event, $localities->get($this->mainLocalityId($cart))) : null;

        $installments = $options->installments;

        if ($installments !== null) {
            $baseTotal = $subtotal + $serviceFee + $paymentFee;

            if (! in_array($installments, $this->installmentPlans($event, $baseTotal), true)) {
                throw new CheckoutException('Los meses sin intereses no están disponibles para esta compra.');
            }

            [$serviceFee, $paymentFee] = $this->installmentFees($paid, $localities, $installments);
        }

        $defender = 0.0;

        if ($options->defender) {
            if (! $this->offersDefender($event)) {
                throw new CheckoutException('Ticket Defender no está disponible para este evento.');
            }

            $defender = array_sum(array_map(
                fn (TicketLine $line) => ($line->price + $line->serviceFee) * (float) config('checkout.defender_rate'),
                $paid,
            ));
        }

        $coupon = null;
        $discount = 0.0;

        if ($options->coupon !== null && trim($options->coupon) !== '') {
            $coupon = $this->coupons->resolve($options->coupon, $event->pk_evento, $this->mainLocalityId($cart), count($paid));
            $discount = $coupon->discount($subtotal, $serviceFee, count($paid));
        }

        return new Quote(
            $lines, $subtotal, $serviceFee, $paymentFee, $parking,
            (float) config('checkout.delivery_fee'), round($defender, 2), $discount, $coupon, $installments,
        );
    }

    /**
     * Plazos ofrecidos: el evento debe aceptarlos y superar el monto mínimo.
     *
     * @return list<int>
     */
    public function installmentPlans(Eventos $event, float $total): array
    {
        if ((int) $event->getAttribute('msi') !== 1 || $total <= (float) config('checkout.installments.min_total')) {
            return [];
        }

        return array_map('intval', array_keys(config('checkout.installments.plans')));
    }

    public function offersDefender(Eventos $event): bool
    {
        return (int) $event->getAttribute('defender') === 1;
    }

    public function installmentRate(int $installments): float
    {
        return (float) (config('checkout.installments.plans')[$installments] ?? 0);
    }

    /**
     * Con meses sin intereses legacy sustituye las comisiones por la tasa del
     * plazo sobre (boletos + cargo web), más el cargo fijo e IVA.
     *
     * @param  list<TicketLine>  $paid
     * @return array{0: float, 1: float}
     */
    private function installmentFees(array $paid, Collection $localities, int $installments): array
    {
        $base = array_sum(array_map(
            fn (TicketLine $line) => $line->price + ((float) $localities->get($line->localityId)?->comisionWeb / 100),
            $paid,
        ));

        $fixed = (float) config('checkout.installments.fixed_fee');
        $vat = (float) config('boxoffice.vat_rate');
        $service = $base * ($this->installmentRate($installments) / 100);
        $payment = $service + ($service + $fixed) * $vat + $fixed;

        return [$service, $payment];
    }

    /**
     * Cargos del estacionamiento según el tipo de comisión del evento.
     *
     * @return array{label: string, price: float, service: float, payment: float}|null
     */
    private function parking(Eventos $event, ?EventosLocalidades $locality): ?array
    {
        $offer = $this->catalog->parkingOffer($event);

        if ($offer === null || $locality === null) {
            return null;
        }

        $price = $offer['price'];
        $rate = (float) EventosComprasTipos::query()->whereKey(PaymentType::Web->value)->value('comision_porcentaje');
        $fixed = (float) config('boxoffice.gateway_fixed_fee');
        $vat = (float) config('boxoffice.vat_rate');
        $bankFee = fn (float $service) => (($price + $service) * ($rate / 100)) + ((($price + $service) * ($rate / 100)) + $fixed) * $vat + $fixed;

        [$service, $payment] = match (CommissionType::tryFrom((int) $event->pk_evento_comision_tipo)) {
            CommissionType::Percentage => [$s = $price * ($locality->comision / 100), $bankFee($s)],
            CommissionType::Fixed => [$s = (float) $event->getAttribute('estacionamiento_comision'), $bankFee($s)],
            CommissionType::PercentageNoCardFee => [$price * ($locality->comisionWeb / 100), 0.0],
            default => [0.0, 0.0],
        };

        return ['label' => $offer['label'], 'price' => $price, 'service' => $service, 'payment' => $payment];
    }

    private function mainLocalityId(WebCart $cart): ?int
    {
        return $cart->selection->localityId ?? ($cart->selection->lines[0] ?? null)?->localityId;
    }

    /**
     * @param  list<TicketLine>  $lines
     * @return Collection<int, EventosLocalidades>
     */
    private function localities(array $lines): Collection
    {
        return EventosLocalidades::query()
            ->whereIn('pk_evento_localidad', collect($lines)->pluck('localityId')->unique())
            ->get()
            ->keyBy('pk_evento_localidad');
    }
}
