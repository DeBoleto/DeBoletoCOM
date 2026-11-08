<?php

namespace App\Modules\BoxOffice\Services\Checkout;

use App\Modules\Events\Enums\CommissionType;

/**
 * Comisiones por boleto (cargo por servicio y cargo bancario).
 *
 * Port de CheckoutManager::ComisionBoletoEvento. Los montos de comisión
 * "web" de la localidad son los que se aplican también en taquilla.
 */
class CommissionCalculator
{
    /**
     * @param  float  $listPrice  Precio de lista de la localidad (para obtener el % de descuento aplicado).
     * @param  float  $serviceFeeBase  comisionWeb de la localidad (o de la localidad especial).
     * @param  float  $paymentFeeRate  comision_porcentaje de la forma de pago.
     * @param  bool  $boxOffice  Venta en efectivo/transferencia: sin IVA sobre la comisión bancaria.
     * @param  bool  $online  Suma el cargo fijo de la pasarela.
     * @return array{service: float, payment: float}
     */
    public function calculate(
        ?CommissionType $type,
        float $price,
        float $listPrice,
        float $serviceFeeBase,
        float $paymentFeeRate,
        bool $boxOffice,
        bool $online,
        bool $tenPercentServiceFee = false,
    ): array {
        if ($price <= 0 || $type === null) {
            return ['service' => 0.0, 'payment' => 0.0];
        }

        $discountFactor = $boxOffice ? 1.0 : $this->discountFactor($listPrice, $price);
        $fixedFee = (float) config('boxoffice.gateway_fixed_fee', 2.5);
        $vat = (float) config('boxoffice.vat_rate', 0.16);

        switch ($type) {
            case CommissionType::Percentage:
                $service = $price * ($serviceFeeBase / 100) * $discountFactor;
                $bank = $price * ($paymentFeeRate / 100);
                $payment = $bank + ($boxOffice ? 0 : ($bank + $fixedFee) * $vat);

                return ['service' => $service, 'payment' => $payment + ($online ? $fixedFee : 0)];

            case CommissionType::Fixed:
                if ($tenPercentServiceFee) {
                    $service = $serviceFeeBase;
                    $bank = round(($price + $service) * 0.10);
                    $payment = $bank + ($boxOffice ? 0 : ($bank + $fixedFee) * $vat + $fixedFee);

                    return ['service' => $service, 'payment' => $payment];
                }

                $service = $serviceFeeBase * $discountFactor;
                $bank = ($price + $service) * ($paymentFeeRate / 100);

                return ['service' => $service, 'payment' => $bank + ($boxOffice ? 0 : $bank * $vat)];

            case CommissionType::PercentageNoCardFee:
                return ['service' => $price * ($serviceFeeBase / 100), 'payment' => 0.0];

            case CommissionType::FixedNoCardFee:
                return ['service' => $serviceFeeBase, 'payment' => 0.0];
        }

        return ['service' => 0.0, 'payment' => 0.0];
    }

    /**
     * Fracción del precio de lista que se cobra (1 = sin descuento).
     */
    private function discountFactor(float $listPrice, float $price): float
    {
        if ($listPrice <= 0) {
            return 1.0;
        }

        $discountPercent = round((($listPrice - $price) / $listPrice) * 100, 2);

        return (100 - $discountPercent) / 100;
    }
}
