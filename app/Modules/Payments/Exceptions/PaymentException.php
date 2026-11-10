<?php

namespace App\Modules\Payments\Exceptions;

use RuntimeException;

/**
 * La pasarela rechazó o no pudo procesar la operación. El mensaje es apto
 * para mostrarse al cliente; `gatewayCode` conserva el código original.
 */
class PaymentException extends RuntimeException
{
    public function __construct(string $message, public readonly ?string $gatewayCode = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
