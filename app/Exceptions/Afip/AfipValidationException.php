<?php

namespace App\Exceptions\Afip;

use RuntimeException;

/**
 * Validación nuestra que falla ANTES de hablar con AFIP (ej. forzar
 * Factura A/B con una empresa que no es Responsable Inscripto, o Factura A
 * a un cliente sin CUIT). Distinta de AfipRejectedException (eso es un
 * rechazo explícito de AFIP) y de AfipConnectionException (falla de red).
 */
class AfipValidationException extends RuntimeException
{
}
