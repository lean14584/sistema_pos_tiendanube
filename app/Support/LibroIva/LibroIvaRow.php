<?php

namespace App\Support\LibroIva;

use App\Enums\TipoComprobante;
use Illuminate\Support\Carbon;

/**
 * Una fila del Libro IVA (Ventas o Compras): un comprobante ya resuelto a
 * los datos que exige AFIP, sin importar si viene de una Invoice o de una
 * Purchase. Simplificación deliberada del modelo de datos de esta app: un
 * comprobante tiene una única alícuota de IVA (no ítems con IVA mixto), por
 * lo que "neto gravado" e "IVA liquidado" son un solo número cada uno en vez
 * de un desglose por alícuota.
 */
final class LibroIvaRow
{
    public function __construct(
        public readonly Carbon $fecha,
        public readonly TipoComprobante $tipoComprobante,
        public readonly int $puntoVenta,
        public readonly int $numeroComprobante,
        public readonly int $codigoDocumento,
        public readonly string $numeroDocumento,
        public readonly string $denominacion,
        public readonly float $importeTotal,
        public readonly float $importeExento,
        public readonly float $importeNetoGravado,
        public readonly float $ivaLiquidado,
        public readonly float $tasaIva,
        public readonly string $codigoOperacion,
    ) {}
}
