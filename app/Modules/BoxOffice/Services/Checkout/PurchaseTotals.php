<?php

namespace App\Modules\BoxOffice\Services\Checkout;

use App\Modules\Events\Enums\CommissionType;
use App\Modules\Events\Models\EventosLocalidades;
use App\Modules\Orders\Enums\PaymentType;
use App\Modules\Orders\Models\EventosCompras;
use App\Modules\Orders\Models\EventosComprasTipos;

/**
 * Totales que se guardan en eventos_compras (port de EventosCompras::update
 * para ventas de taquilla).
 */
class PurchaseTotals
{
    public function apply(
        EventosCompras $purchase,
        ?CommissionType $commissionType,
        EventosLocalidades $locality,
        PaymentType $paymentType,
        int $promotion,
        float $localCommission,
    ): void {
        $tickets = $purchase->boletos()->visibles()->get(['precio']);
        $ticketCount = $tickets->count();
        $total = (float) $tickets->sum('precio');

        $appCommission = match ($commissionType) {
            CommissionType::Percentage, CommissionType::PercentageNoCardFee => $total * ($locality->comision / 100),
            CommissionType::Fixed => $ticketCount * $locality->comision,
            CommissionType::FixedNoCardFee => $promotion === 1 ? (float) (int) $localCommission : $ticketCount * $locality->comision,
            default => 0.0,
        };

        $cardCommission = 0.0;

        if ($paymentType === PaymentType::Terminal && ! in_array($purchase->pk_evento, config('boxoffice.rules.card_fee_exempt_events'), true)) {
            $rate = (float) EventosComprasTipos::query()->whereKey($paymentType->value)->value('comision_porcentaje');
            $cardCommission = ($total + ($ticketCount * $locality->comision)) * ($rate / 100);
        }

        if (in_array($paymentType, [PaymentType::Courtesy, PaymentType::Exchange], true)) {
            $appCommission = 0.0;
            $cardCommission = 0.0;
        }

        $purchase->forceFill([
            'total_compra' => $total,
            'total_comision_aplicacion' => $appCommission,
            'total_comision_tarjeta' => $cardCommission,
            'total_comision_pago_linea' => 0,
            'total_utilidad' => $total - $appCommission - $cardCommission,
        ])->save();
    }
}
