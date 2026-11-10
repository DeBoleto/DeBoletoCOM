<?php

namespace App\Modules\Checkout\Exceptions;

class SeatHeldException extends CheckoutException
{
    public static function for(string $seat): self
    {
        return new self("El asiento {$seat} ya no está disponible. Elige otro.");
    }
}
