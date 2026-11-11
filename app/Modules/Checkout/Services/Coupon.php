<?php

namespace App\Modules\Checkout\Services;

use App\Modules\BoxOffice\Services\Checkout\TicketLine;
use App\Modules\Orders\Models\EventoCodigoPromocion;

/**
 * Código promocional válido para un carrito.
 */
final class Coupon
{
    public function __construct(
        public readonly int $id,
        public readonly string $code,
        public readonly int $type,
        public readonly float $value,
        public readonly ?int $localityId = null,
    ) {}

    public static function from(EventoCodigoPromocion $promotion): self
    {
        return new self(
            $promotion->pk_evento_codigo_promocion,
            $promotion->codigo_promocion,
            $promotion->tipo,
            $promotion->promocion,
            $promotion->pk_localidad,
        );
    }

    /**
     * Descuento sobre boletos (y cargo por servicio en los porcentuales).
     */
    public function discount(float $subtotal, float $serviceFee, int $paidTickets): float
    {
        $discount = match ($this->type) {
            EventoCodigoPromocion::TYPE_PERCENTAGE => ($subtotal + $serviceFee) * ($this->value / 100),
            EventoCodigoPromocion::TYPE_FREE_TICKETS => $paidTickets > 0
                ? intdiv($paidTickets, (int) $this->value + 1) * ($subtotal / $paidTickets)
                : 0.0,
            default => $this->value,
        };

        return round(min(max($discount, 0.0), $subtotal + $serviceFee), 2);
    }

    /**
     * El descuento "N+1 x N" ya se refleja en los boletos gratis; los demás
     * se reparten en el precio de cada boleto pagado (como en legacy).
     */
    public function spreadsOverTickets(): bool
    {
        return $this->type !== EventoCodigoPromocion::TYPE_FREE_TICKETS;
    }

    public function description(): string
    {
        return match ($this->type) {
            EventoCodigoPromocion::TYPE_PERCENTAGE => rtrim(rtrim(number_format($this->value, 2), '0'), '.').'% de descuento',
            EventoCodigoPromocion::TYPE_FREE_TICKETS => ((int) $this->value + 1).'x'.(int) $this->value.' en tus boletos',
            default => '$'.number_format($this->value, 2).' de descuento',
        };
    }

    /**
     * Un código ligado a una zona solo descuenta los boletos de esa zona.
     */
    public function covers(TicketLine $line): bool
    {
        return $this->localityId === null || $this->localityId === $line->localityId;
    }

    public function toArray(): array
    {
        return ['code' => $this->code, 'description' => $this->description()];
    }
}
