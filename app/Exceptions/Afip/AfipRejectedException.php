<?php

namespace App\Exceptions\Afip;

use RuntimeException;

/**
 * AFIP respondió explícitamente Resultado=R (rechazado). El mensaje ya
 * viene armado para mostrarse tal cual al usuario, no es un error interno.
 */
class AfipRejectedException extends RuntimeException
{
    /**
     * @param  array<int, array{code: string, msg: string}>  $errores
     */
    public function __construct(string $message, public readonly array $errores = [])
    {
        parent::__construct($message);
    }
}
