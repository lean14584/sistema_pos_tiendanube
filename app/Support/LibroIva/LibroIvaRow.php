<?php

namespace App\Support\LibroIva;

use App\Enums\TipoComprobante;
use Illuminate\Support\Carbon;

/**
 * Un comprobante del Libro IVA (Ventas o Compras): los datos que exige AFIP,
 * sin importar si viene de una Invoice o de una Purchase. Un comprobante puede
 * tener varias alícuotas de IVA (21% + 10,5% + exento), así que el neto
 * gravado y el IVA se guardan desglosados en $alicuotas; los totales agregados
 * (importeNetoGravado, ivaLiquidado) se derivan de ese desglose.
 */
final class LibroIvaRow
{
    public readonly float $importeNetoGravado;

    public readonly float $ivaLiquidado;

    /**
     * @param  array<int, LibroIvaAlicuota>  $alicuotas  Solo las gravadas (tasa > 0).
     */
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
        public readonly array $alicuotas,
        public readonly string $codigoOperacion,
    ) {
        $this->importeNetoGravado = array_sum(array_map(fn (LibroIvaAlicuota $a) => $a->netoGravado, $alicuotas));
        $this->ivaLiquidado = array_sum(array_map(fn (LibroIvaAlicuota $a) => $a->ivaLiquidado, $alicuotas));
    }
}
