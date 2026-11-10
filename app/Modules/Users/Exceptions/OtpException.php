<?php

namespace App\Modules\Users\Exceptions;

use RuntimeException;

/**
 * Falla del proveedor de códigos de verificación. El mensaje es apto para el cliente.
 */
class OtpException extends RuntimeException {}
