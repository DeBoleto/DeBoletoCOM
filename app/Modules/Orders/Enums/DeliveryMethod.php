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
}
