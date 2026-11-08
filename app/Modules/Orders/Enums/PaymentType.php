<?php

namespace App\Modules\Orders\Enums;

/**
 * Catálogo eventos_compras_tipos.pk_evento_compra_tipo.
 */
enum PaymentType: int
{
    case Cash = 1;
    case Terminal = 2;
    case Web = 3;
    case Courtesy = 4;
    case Exchange = 5;
    case Transfer = 6;

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Efectivo',
            self::Terminal => 'Terminal bancaria',
            self::Web => 'Venta web',
            self::Courtesy => 'Cortesía',
            self::Exchange => 'Canje',
            self::Transfer => 'Transferencia',
        };
    }

    /**
     * @return list<self>
     */
    public static function boxOffice(): array
    {
        return [self::Cash, self::Terminal, self::Transfer];
    }
}
