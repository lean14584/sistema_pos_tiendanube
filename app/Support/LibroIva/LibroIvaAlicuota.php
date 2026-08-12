<?php

namespace App\Support\LibroIva;

/**
 * Una alícuota gravada dentro de un comprobante del Libro IVA: base imponible
 * (neto gravado) e IVA liquidado a esa tasa. Un comprobante con alícuotas
 * mezcladas (21% + 10,5%, etc.) tiene una de estas por cada tasa.
 */
final class LibroIvaAlicuota
{
    public function __construct(
        public readonly float $tasa,
        public readonly float $netoGravado,
        public readonly float $ivaLiquidado,
    ) {}
}
