<?php

namespace App\Services\Afip\Data;

use App\Enums\TipoComprobante;

final readonly class CaeRequest
{
    /**
     * @param  float  $impNeto  Neto gravado (base de las alícuotas de IVA).
     * @param  float  $impOpEx  Importe de operaciones exentas / no gravadas.
     * @param  array<int, array{tasa: float, baseImp: float, importe: float}>  $alicuotas
     *         Desglose del IVA por alícuota (solo gravadas). Vacío si no hay IVA.
     */
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
        public float $impOpEx = 0.0,
        public array $alicuotas = [],
        public int $concepto = 1, // Productos — único concepto soportado hoy
        public ?ComprobanteAsociado $comprobanteAsociado = null,
    ) {
    }
}
