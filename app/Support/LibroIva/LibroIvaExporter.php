<?php

namespace App\Support\LibroIva;

use App\Support\Afip\AlicuotaResolver;
use Illuminate\Support\Collection;

/**
 * Genera los 4 archivos de ancho fijo del "Libro de IVA Digital" (RG 4597)
 * según el diseño de registro oficial de AFIP (LIBRO_IVA_DIGITAL_VENTAS_CBTE,
 * _VENTAS_ALICUOTAS, _COMPRAS_CBTE, _COMPRAS_ALICUOTAS). Fin de registro
 * 0D0A (CRLF) por especificación, código ASCII.
 *
 * Simplificaciones deliberadas frente al diseño completo de AFIP: sin
 * moneda extranjera (siempre PES, tipo de cambio 1), sin despacho de
 * importación, sin corredor/comisionista — ninguno de estos casos existe en
 * el modelo de datos de esta app.
 */
final class LibroIvaExporter
{
    private const EOL = "\r\n";

    public static function ventasCbte(Collection $rows): string
    {
        return $rows->map(function (LibroIvaRow $row) {
            $numero = self::numZero((string) $row->numeroComprobante, 20);

            return
                self::fecha($row->fecha).
                self::numZero((string) $row->tipoComprobante->value, 3).
                self::numZero((string) $row->puntoVenta, 5).
                $numero.
                $numero.
                self::numZero((string) $row->codigoDocumento, 2).
                self::numZero($row->numeroDocumento, 20).
                self::alfa($row->denominacion, 30).
                self::importe($row->importeTotal, 15).
                self::importe(0, 15).
                self::importe(0, 15).
                self::importe($row->importeExento, 15).
                self::importe(0, 15).
                self::importe(0, 15).
                self::importe(0, 15).
                self::importe(0, 15).
                'PES'.
                self::tipoCambio().
                '1'.
                self::codigoOperacion($row->codigoOperacion).
                self::importe(0, 15).
                self::fecha($row->fecha);
        })->implode(self::EOL).self::EOL;
    }

    public static function ventasAlicuotas(Collection $rows): string
    {
        return $rows
            ->filter(fn (LibroIvaRow $row) => $row->tasaIva > 0.0)
            ->map(fn (LibroIvaRow $row) =>
                self::numZero((string) $row->tipoComprobante->value, 3).
                self::numZero((string) $row->puntoVenta, 5).
                self::numZero((string) $row->numeroComprobante, 20).
                self::importe($row->importeNetoGravado, 15).
                AlicuotaResolver::codigo($row->tasaIva).
                self::importe($row->ivaLiquidado, 15)
            )
            ->implode(self::EOL).self::EOL;
    }

    public static function comprasCbte(Collection $rows): string
    {
        return $rows->map(function (LibroIvaRow $row) {
            return
                self::fecha($row->fecha).
                self::numZero((string) $row->tipoComprobante->value, 3).
                self::numZero((string) $row->puntoVenta, 5).
                self::numZero((string) $row->numeroComprobante, 20).
                self::alfa('', 16). // despacho de importación: no aplica
                self::numZero((string) $row->codigoDocumento, 2).
                self::numZero($row->numeroDocumento, 20).
                self::alfa($row->denominacion, 30).
                self::importe($row->importeTotal, 15).
                self::importe(0, 15).
                self::importe($row->importeExento, 15).
                self::importe(0, 15).
                self::importe(0, 15).
                self::importe(0, 15).
                self::importe(0, 15).
                self::importe(0, 15).
                'PES'.
                self::tipoCambio().
                '1'.
                self::codigoOperacion($row->codigoOperacion).
                self::importe($row->ivaLiquidado, 15). // crédito fiscal computable
                self::importe(0, 15).
                self::numZero('', 11). // CUIT emisor/corredor: no aplica
                self::alfa('', 30).
                self::importe(0, 15);
        })->implode(self::EOL).self::EOL;
    }

    public static function comprasAlicuotas(Collection $rows): string
    {
        return $rows
            ->filter(fn (LibroIvaRow $row) => $row->tasaIva > 0.0)
            ->map(fn (LibroIvaRow $row) =>
                self::numZero((string) $row->tipoComprobante->value, 3).
                self::numZero((string) $row->puntoVenta, 5).
                self::numZero((string) $row->numeroComprobante, 20).
                self::numZero((string) $row->codigoDocumento, 2).
                self::numZero($row->numeroDocumento, 20).
                self::importe($row->importeNetoGravado, 15).
                AlicuotaResolver::codigo($row->tasaIva).
                self::importe($row->ivaLiquidado, 15)
            )
            ->implode(self::EOL).self::EOL;
    }

    private static function fecha(\Illuminate\Support\Carbon $fecha): string
    {
        return $fecha->format('Ymd');
    }

    /**
     * Peso a peso: esta app no maneja moneda extranjera, así que el tipo de
     * cambio siempre es 1 (4 enteros + 6 decimales, sin punto).
     */
    private static function tipoCambio(): string
    {
        return str_pad('1000000', 10, '0', STR_PAD_LEFT);
    }

    private static function codigoOperacion(string $codigo): string
    {
        return $codigo === '' ? ' ' : $codigo;
    }

    private static function alfa(string $value, int $len): string
    {
        return str_pad(mb_substr($value, 0, $len), $len, ' ', STR_PAD_RIGHT);
    }

    private static function numZero(string $digits, int $len): string
    {
        $digits = preg_replace('/\D/', '', $digits) ?? '';
        $padded = str_pad($digits, $len, '0', STR_PAD_LEFT);

        return mb_substr($padded, -$len);
    }

    /**
     * 13 enteros + 2 decimales, sin punto ni coma (ej.: $1234,56 -> 15
     * dígitos terminados en "123456").
     */
    private static function importe(float $value, int $len): string
    {
        return str_pad((string) (int) round($value * 100), $len, '0', STR_PAD_LEFT);
    }
}
