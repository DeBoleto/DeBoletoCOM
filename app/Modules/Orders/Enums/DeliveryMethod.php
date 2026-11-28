<?php

namespace App\Modules\Orders\Enums;

/**
 * Valores de eventos_compras.entrega_medio.
 */
enum DeliveryMethod: string
{
    case Printed = 'impreso';
    case Digital = 'digital';
    case WhatsApp = 'whatsapp';
    case Email = 'correo';

    public function label(): string
    {
        return match ($this) {
            self::Printed => 'Impreso en taquilla',
            self::Digital => 'PDF generado',
            self::WhatsApp => 'Enviado por WhatsApp',
            self::Email => 'Enviado por correo',
        };
    }

    public static function labelFor(?string $value): string
    {
        return self::tryFrom((string) $value)?->label() ?? 'Sin registro';
    }
}
