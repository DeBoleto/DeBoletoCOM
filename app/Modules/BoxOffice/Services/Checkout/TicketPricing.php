<?php

namespace App\Modules\BoxOffice\Services\Checkout;

use App\Modules\Events\Enums\CommissionType;
use App\Modules\Events\Models\Eventos;
use App\Modules\Events\Models\EventosLocalidades;
use App\Modules\Events\Models\EventosLocalidadesEspeciales;
use App\Modules\Events\Models\Views\EventoVista;
use App\Modules\Orders\Enums\PaymentType;
use App\Modules\Orders\Models\EventosComprasTipos;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Convierte la selección de la taquilla en líneas de boleto con precio,
 * promociones (2x1, 3x2, 4x2), descuentos y comisiones.
 */
class TicketPricing
{
    public function __construct(private readonly CommissionCalculator $commissions) {}

    /**
     * Venta con mapa: una localidad, asientos numerados o cupo general.
     *
     * @param  list<string>  $seats  Ids de asiento o "NA_NA" por cada boleto de cupo general.
     * @param  array<string, int>  $specials  asiento => pk_evento_localidad_especiales.
     * @param  list<string>  $extraChairs  Asientos (mesas) con silla adicional.
     */
    public function forSeatMap(
        EventoVista $event,
        EventosLocalidades $locality,
        PaymentType $paymentType,
        array $seats,
        bool $applyPromotion,
        ?int $discount,
        array $specials = [],
        array $extraChairs = [],
    ): PricedSelection {
        $rules = config('boxoffice.rules');
        $promotionDisabled = in_array($event->pk_evento, $rules['promotion_disabled_events'], true);
        $eventPercent = $promotionDisabled ? 0.0 : (float) Eventos::query()->whereKey($event->pk_evento)->value('promocion_porcentaje');

        $promotion = $this->basePromotion($event, $applyPromotion);
        if ($locality->promocion > 0) {
            $promotion = $locality->promocion;
        }
        if ($discount) {
            $promotion = 0;
        }

        $specialModels = $this->specialsFor($locality, $specials);
        $paymentModel = $this->paymentModel($paymentType);
        $boxOffice = $paymentType !== PaymentType::Terminal;
        $fourForTwo = (int) config('boxoffice.four_for_two_code', 42);
        $promoBlockLimit = intdiv(count($seats), 4) * 4;
        $extraChair = $this->extraChairRule($event, $locality);

        $lines = [];
        $counter = 0;
        $promotionText = '';

        foreach (array_values($seats) as $index => $seatId) {
            $seat = $seatId === 'NA_NA' ? 'NA_NA'.$index : $seatId;
            $price = $this->seatMapPrice($event, $locality, $eventPercent, $promotionDisabled, $discount);
            $special = $specialModels->get($seatId === 'NA_NA' ? 'Boleto'.($index + 1) : $seatId);

            if ($special) {
                $price = $special->precio;
            }

            if ($promotion === 0) {
                $fees = $this->fees($event, $locality, $paymentModel, $price, $special, $boxOffice, online: false);
                $lines[] = new TicketLine(
                    $locality->pk_evento_localidad, $locality->evento_localidad, $seat, $price,
                    $fees['service'], $fees['payment'], false, $special?->pk_evento_localidad_especiales,
                    $special?->evento_localidad ?? 'Regular',
                );

                if ($extraChair && in_array($seatId, $extraChairs, true)) {
                    $lines[] = new TicketLine(
                        $locality->pk_evento_localidad, $locality->evento_localidad,
                        $seatId.$extraChair['seat_suffix'], (float) $extraChair['price'], (float) $extraChair['commission'],
                    );
                }

                continue;
            }

            if ($promotion === $fourForTwo) {
                $counter++;
                $promotionText = '4X2';
                $position = $counter % 4;
                $isCourtesy = $counter <= $promoBlockLimit && ($position === 3 || $position === 0);
            } else {
                $promotionText = (1 + $promotion).'X'.$promotion;
                $isCourtesy = $counter >= $promotion;
                $counter = $isCourtesy ? 0 : $counter + 1;
            }

            if ($isCourtesy) {
                $lines[] = TicketLine::courtesy(
                    $locality->pk_evento_localidad, $locality->evento_localidad, $seat,
                    $special?->pk_evento_localidad_especiales, $special?->evento_localidad ?? 'Regular',
                );

                continue;
            }

            $fees = $this->fees($event, $locality, $paymentModel, $price, $special, $boxOffice, online: false);
            $lines[] = new TicketLine(
                $locality->pk_evento_localidad, $locality->evento_localidad, $seat, $price,
                $fees['service'], $fees['payment'], false, $special?->pk_evento_localidad_especiales,
                $special?->evento_localidad ?? 'Regular',
            );
        }

        return new PricedSelection($lines, $promotion, $promotionText);
    }

    /**
     * Venta sin mapa (carreras / eventos sin estructura): cantidad por localidad.
     *
     * @param  Collection<int, EventosLocalidades>  $localities
     * @param  array<int, int>  $quantities  pk_evento_localidad => cantidad.
     */
    public function forQuantities(
        EventoVista $event,
        Collection $localities,
        PaymentType $paymentType,
        array $quantities,
        bool $applyPromotion,
        ?int $discount,
    ): PricedSelection {
        $basePromotion = $this->basePromotion($event, $applyPromotion);
        $paidPromotionPrice = config('boxoffice.rules.promotion_paid_price')[$event->pk_evento] ?? null;
        $paymentModel = $this->paymentModel($paymentType);

        $lines = [];
        $counter = 0;
        $promotionText = '';

        foreach ($localities as $locality) {
            $quantity = (int) ($quantities[$locality->pk_evento_localidad] ?? 0);
            $promotion = $locality->promocion > 0 ? $locality->promocion : $basePromotion;

            for ($i = 0; $i < $quantity; $i++) {
                $price = $locality->precio;
                if ($discount) {
                    $price -= $price * ($discount / 100);
                }

                if ($promotion > 0) {
                    $promotionText = (1 + $promotion).'X'.$promotion;
                    $isCourtesy = $counter >= $promotion;
                    $counter = $isCourtesy ? 0 : $counter + 1;

                    if ($isCourtesy) {
                        if ($paidPromotionPrice !== null) {
                            $fees = $this->fees($event, $locality, $paymentModel, (float) $paidPromotionPrice, null, true, online: true);
                            $lines[] = new TicketLine(
                                $locality->pk_evento_localidad, $locality->evento_localidad, 'NA_NA',
                                (float) $paidPromotionPrice, $fees['service'], $fees['payment'],
                            );
                        } else {
                            $lines[] = TicketLine::courtesy($locality->pk_evento_localidad, $locality->evento_localidad, 'NA_NA');
                        }

                        continue;
                    }
                }

                $fees = $this->fees($event, $locality, $paymentModel, $price, null, true, online: true);
                $lines[] = new TicketLine(
                    $locality->pk_evento_localidad, $locality->evento_localidad, 'NA_NA', $price,
                    $fees['service'], $fees['payment'],
                );
            }
        }

        return new PricedSelection($lines, $basePromotion, $promotionText);
    }

    private function basePromotion(EventoVista $event, bool $applyPromotion): int
    {
        if ($applyPromotion) {
            return 1;
        }

        if (in_array($event->pk_evento, config('boxoffice.rules.promotion_disabled_events'), true)) {
            return 0;
        }

        return (int) $event->promocion;
    }

    private function seatMapPrice(EventoVista $event, EventosLocalidades $locality, float $eventPercent, bool $promotionDisabled, ?int $discount): float
    {
        $price = $locality->precio;

        if ($eventPercent > 0) {
            $price -= $price * ($eventPercent / 100);
        }

        if (! $promotionDisabled && $locality->promocion_porcentaje > 0) {
            $price -= $price * ($locality->promocion_porcentaje / 100);
        }

        if ($discount) {
            $overPromoted = in_array($event->pk_evento, config('boxoffice.rules.discount_over_promoted_price_events'), true);
            $price = $overPromoted
                ? $price - ($price * ($discount / 100))
                : $locality->precio - ($locality->precio * ($discount / 100));
        }

        return $price;
    }

    /**
     * Localidades especiales elegidas, validadas contra la localidad: el precio
     * siempre sale de la base, nunca del formulario.
     *
     * @param  array<string, int>  $specials
     * @return Collection<string, EventosLocalidadesEspeciales>
     */
    private function specialsFor(EventosLocalidades $locality, array $specials): Collection
    {
        if ($specials === []) {
            return collect();
        }

        $models = $locality->especiales()
            ->whereIn('pk_evento_localidad_especiales', array_values($specials))
            ->get()
            ->keyBy('pk_evento_localidad_especiales');

        return collect($specials)->map(function ($specialId, $seat) use ($models) {
            return $models->get((int) $specialId) ?? throw ValidationException::withMessages([
                'specials' => "El tipo de boleto elegido para {$seat} no pertenece a esta localidad.",
            ]);
        });
    }

    private function paymentModel(PaymentType $paymentType): EventosComprasTipos
    {
        return EventosComprasTipos::query()->findOrFail($paymentType->value);
    }

    /**
     * @return array{service: float, payment: float}
     */
    private function fees(
        EventoVista $event,
        EventosLocalidades $locality,
        EventosComprasTipos $payment,
        float $price,
        ?EventosLocalidadesEspeciales $special,
        bool $boxOffice,
        bool $online,
    ): array {
        $rule = config('boxoffice.rules.ten_percent_service_fee');
        $tenPercent = in_array($event->pk_evento, $rule['events'], true)
            || in_array($event->pk_evento_categoria, $rule['categories'], true);

        $type = CommissionType::tryFrom((int) $event->pk_evento_comision_tipo);
        $useSpecialFee = $special && $type === CommissionType::Fixed && ! $tenPercent;

        return $this->commissions->calculate(
            $type,
            $price,
            $locality->precio,
            $useSpecialFee ? $special->comisionWeb : $locality->comisionWeb,
            $payment->comision_porcentaje,
            $boxOffice,
            $online,
            $tenPercent,
        );
    }

    /**
     * @return array{seat_suffix: string, price: float, commission: float}|null
     */
    private function extraChairRule(EventoVista $event, EventosLocalidades $locality): ?array
    {
        $rule = config('boxoffice.rules.extra_chair');

        if (! in_array($event->pk_evento, $rule['events'], true) || ! in_array($locality->pk_evento_localidad, $rule['localities'], true)) {
            return null;
        }

        return $rule;
    }
}
