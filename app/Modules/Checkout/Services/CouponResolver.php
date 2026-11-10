<?php

namespace App\Modules\Checkout\Services;

use App\Modules\Checkout\Exceptions\CheckoutException;
use App\Modules\Orders\Models\EventoCodigoPromocion;
use App\Modules\Shared\Models\LegacyModel;
use Illuminate\Support\Carbon;

class CouponResolver
{
    /**
     * @throws CheckoutException cuando el código no aplica.
     */
    public function resolve(string $code, int $eventId, ?int $localityId, int $paidTickets): Coupon
    {
        $promotion = EventoCodigoPromocion::query()
            ->where('codigo_promocion', trim($code))
            ->vigentes(Carbon::now(LegacyModel::legacyTimezone()))
            ->get()
            ->first(fn (EventoCodigoPromocion $promotion) => $promotion->appliesTo($eventId, $localityId));

        if ($promotion === null) {
            throw new CheckoutException('El código no existe, ya caducó o no aplica para estos boletos.');
        }

        if ($promotion->boletos && $paidTickets < $promotion->boletos) {
            throw new CheckoutException("Este código aplica a partir de {$promotion->boletos} boletos.");
        }

        if ($promotion->tipo === EventoCodigoPromocion::TYPE_FREE_TICKETS && $paidTickets <= (int) $promotion->promocion) {
            $needed = (int) $promotion->promocion + 1;

            throw new CheckoutException("Elige al menos {$needed} boletos para aplicar este código.");
        }

        return Coupon::from($promotion);
    }
}
