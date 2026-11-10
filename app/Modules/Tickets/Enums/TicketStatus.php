<?php

namespace App\Modules\Tickets\Enums;

/**
 * Catálogo eventos_funciones_boletos.pk_evento_funcion_boleto_status.
 */
enum TicketStatus: int
{
    case Available = 1;
    case Issued = 2;
    case Blocked = 3;
    case Pending = 4;
    case Replaced = 5;
    case ChargedBack = 7;

    public function isTaken(): bool
    {
        return $this === self::Issued || $this === self::Pending;
    }
}
