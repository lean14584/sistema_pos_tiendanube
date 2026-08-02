<?php

namespace App\Support\LibroIva;

use App\Models\Invoice;
use App\Models\Purchase;
use Illuminate\Support\Collection;

/**
 * Arma las filas del Libro IVA Ventas/Compras para un período, a partir de
 * los comprobantes ya fiscales de esta app (facturas con CAE, compras
 * cargadas con los datos del comprobante del proveedor).
 */
final class LibroIvaCalculator
{
    /**
     * @return Collection<int, LibroIvaRow>
     */
    public static function ventas(string $desde, string $hasta): Collection
    {
        return Invoice::query()
            ->whereNotNull('cae')
            ->whereDate('issue_date', '>=', $desde)
            ->whereDate('issue_date', '<=', $hasta)
            ->with('client', 'items')
            ->orderBy('issue_date')
            ->orderBy('punto_venta')
            ->orderBy('tipo_comprobante')
            ->orderBy('numero_comprobante_afip')
            ->get()
            ->map(fn (Invoice $invoice) => self::fromInvoice($invoice));
    }

    /**
     * @return Collection<int, LibroIvaRow>
     */
    public static function compras(string $desde, string $hasta): Collection
    {
        return Purchase::query()
            ->whereNot('status', 'draft')
            ->whereNotNull('tipo_comprobante')
            ->whereDate('issue_date', '>=', $desde)
            ->whereDate('issue_date', '<=', $hasta)
            ->with('provider', 'items')
            ->orderBy('issue_date')
            ->orderBy('punto_venta')
            ->orderBy('tipo_comprobante')
            ->orderBy('numero_comprobante')
            ->get()
            ->map(fn (Purchase $purchase) => self::fromPurchase($purchase));
    }

    /**
     * Totales agrupados por alícuota, para la sección "resumen" de la
     * pantalla y para el archivo ALICUOTAS del export.
     *
     * @param  Collection<int, LibroIvaRow>  $rows
     * @return Collection<int, array{tasa: float, netoGravado: float, iva: float}>
     */
    public static function resumenPorAlicuota(Collection $rows): Collection
    {
        return $rows
            ->filter(fn (LibroIvaRow $row) => $row->tasaIva > 0.0)
            ->groupBy(fn (LibroIvaRow $row) => number_format($row->tasaIva, 2, '.', ''))
            ->map(fn (Collection $group, string $tasa) => [
                'tasa' => (float) $tasa,
                'netoGravado' => $group->sum('importeNetoGravado'),
                'iva' => $group->sum('ivaLiquidado'),
            ])
            ->sortBy('tasa')
            ->values();
    }

    private static function fromInvoice(Invoice $invoice): LibroIvaRow
    {
        $tasa = (float) $invoice->tax_rate;
        $exento = $tasa <= 0.0;

        return new LibroIvaRow(
            fecha: $invoice->issue_date,
            tipoComprobante: $invoice->tipo_comprobante,
            puntoVenta: $invoice->punto_venta,
            numeroComprobante: $invoice->numero_comprobante_afip,
            codigoDocumento: $invoice->client->tipo_documento->afipCode(),
            numeroDocumento: $invoice->client->tax_id ?: '0',
            denominacion: $invoice->client->name,
            importeTotal: (float) $invoice->total,
            importeExento: $exento ? (float) $invoice->subtotal : 0.0,
            importeNetoGravado: $exento ? 0.0 : (float) $invoice->subtotal,
            ivaLiquidado: (float) $invoice->tax_amount,
            tasaIva: $tasa,
            codigoOperacion: $exento ? 'E' : '',
        );
    }

    private static function fromPurchase(Purchase $purchase): LibroIvaRow
    {
        $tasa = (float) $purchase->tax_rate;
        $exento = $tasa <= 0.0;

        return new LibroIvaRow(
            fecha: $purchase->issue_date,
            tipoComprobante: $purchase->tipo_comprobante,
            puntoVenta: $purchase->punto_venta,
            numeroComprobante: $purchase->numero_comprobante,
            codigoDocumento: $purchase->provider->tipo_documento->afipCode(),
            numeroDocumento: $purchase->provider->tax_id ?: '0',
            denominacion: $purchase->provider->name,
            importeTotal: (float) $purchase->total,
            importeExento: $exento ? (float) $purchase->subtotal : 0.0,
            importeNetoGravado: $exento ? 0.0 : (float) $purchase->subtotal,
            ivaLiquidado: (float) $purchase->tax_amount,
            tasaIva: $tasa,
            codigoOperacion: $exento ? 'E' : '',
        );
    }
}
