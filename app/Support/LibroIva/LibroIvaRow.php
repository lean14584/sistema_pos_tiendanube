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

    /**
     * Representación en array plano (fecha/enum a primitivos) para poder
     * guardar esto en cache sin depender de que serialize()/unserialize()
     * de objetos PHP (con propiedades readonly, Carbon, enums anidados)
     * sobreviva intacto entre el request que escribe la cache y el que la
     * lee — un blob cacheado así puede quedar corrupto si en el medio hay
     * un deploy (ver MEJORA en LibroIvaCalculator).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'fecha' => $this->fecha->toDateString(),
            'tipoComprobante' => $this->tipoComprobante->value,
            'puntoVenta' => $this->puntoVenta,
            'numeroComprobante' => $this->numeroComprobante,
            'codigoDocumento' => $this->codigoDocumento,
            'numeroDocumento' => $this->numeroDocumento,
            'denominacion' => $this->denominacion,
            'importeTotal' => $this->importeTotal,
            'importeExento' => $this->importeExento,
            'alicuotas' => array_map(fn (LibroIvaAlicuota $a) => $a->toArray(), $this->alicuotas),
            'codigoOperacion' => $this->codigoOperacion,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            fecha: Carbon::parse($data['fecha']),
            tipoComprobante: TipoComprobante::from($data['tipoComprobante']),
            puntoVenta: $data['puntoVenta'],
            numeroComprobante: $data['numeroComprobante'],
            codigoDocumento: $data['codigoDocumento'],
            numeroDocumento: $data['numeroDocumento'],
            denominacion: $data['denominacion'],
            importeTotal: $data['importeTotal'],
            importeExento: $data['importeExento'],
            alicuotas: array_map(fn (array $a) => LibroIvaAlicuota::fromArray($a), $data['alicuotas']),
            codigoOperacion: $data['codigoOperacion'],
        );
    }
}
