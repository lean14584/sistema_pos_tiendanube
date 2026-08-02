<?php

namespace App\Services\Afip\Data;

use App\Enums\TipoComprobante;

/**
 * El "CbtesAsoc" que AFIP exige en toda Nota de Crédito/Débito: a qué
 * comprobante corrige.
 */
final readonly class ComprobanteAsociado
{
    public function __construct(
        public TipoComprobante $tipo,
        public int $puntoVenta,
        public int $numero,
    ) {
    }
}
