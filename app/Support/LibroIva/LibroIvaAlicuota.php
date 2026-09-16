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

    /**
     * @return array{tasa: float, netoGravado: float, ivaLiquidado: float}
     */
    public function toArray(): array
    {
        return [
            'tasa' => $this->tasa,
            'netoGravado' => $this->netoGravado,
            'ivaLiquidado' => $this->ivaLiquidado,
        ];
    }

    /**
     * @param  array{tasa: float, netoGravado: float, ivaLiquidado: float}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['tasa'], $data['netoGravado'], $data['ivaLiquidado']);
    }
}
