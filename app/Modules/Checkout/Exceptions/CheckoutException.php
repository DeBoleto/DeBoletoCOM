<?php

namespace App\Modules\Checkout\Exceptions;

use RuntimeException;

/**
 * Regla de negocio que impide continuar con la compra. El mensaje es apto para el cliente.
 */
class CheckoutException extends RuntimeException {}
