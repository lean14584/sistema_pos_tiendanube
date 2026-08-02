<?php

namespace App\Exceptions\Afip;

use RuntimeException;

/**
 * Fallo de red/SOAP/certificado al hablar con AFIP — no es un rechazo
 * explícito, es que no se pudo completar la conversación.
 */
class AfipConnectionException extends RuntimeException
{
}
