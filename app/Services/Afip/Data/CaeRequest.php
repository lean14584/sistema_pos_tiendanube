<?php

namespace App\Services\Afip\Data;

use App\Enums\TipoComprobante;

final readonly class CaeRequest
{
    public function __construct(
        public int $puntoVenta,
        public TipoComprobante $tipoComprobante,
        public int $cbteNro,
        public int $docTipo,
        public string $docNro,
        public float $impNeto,
        public float $impIva,
        public float $impTotal,
        public int $condicionIvaReceptorId,
        public int $concepto = 1, // Productos — único concepto soportado hoy
        public ?ComprobanteAsociado $comprobanteAsociado = null,
    ) {
    }
}
