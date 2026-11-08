<?php

namespace App\Modules\BoxOffice\Exceptions;

use RuntimeException;

class SeatsUnavailableException extends RuntimeException
{
    public function __construct(string $message = 'Uno o más asientos ya no están disponibles. Selecciona otros.')
    {
        parent::__construct($message);
    }
}
