<?php

namespace App\Support\LibroIva;

use App\Models\Invoice;
use App\Models\Purchase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

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
        // MEJORA: sin caché, cada render de la pantalla (y cada export) volvía
        // a traer y recorrer todos los comprobantes fiscales del período con
        // sus ítems eager-cargados. Mismo criterio que SalesReport::build().
        //
        // MEJORA: se cachea el array plano (LibroIvaRow::toArray()), no los
        // objetos LibroIvaRow directamente - Cache::remember() serializa el
        // valor con serialize() nativo de PHP, y estos objetos tienen
        // propiedades readonly + Carbon + enum anidados; si un deploy cae
        // justo dentro de la ventana de 60s entre el write y el read de la
        // cache, unserialize() puede devolver algo que ya no matchea la
        // clase esperada (visto en producción: quedó un
        // Illuminate\Database\Eloquent\Collection vacío cacheado bajo esta
        // key en vez del array de LibroIvaRow). Un array de escalares no
        // tiene ese riesgo.
        //
        // MEJORA: whereDate() envuelve la columna en DATE(...), lo que
        // impide usar el índice de issue_date - mismo criterio que
        // SalesReport::buildUncached(). El corte superior usa "< día
        // siguiente" para seguir siendo un rango sargable.
        $rows = Cache::remember("libro-iva:ventas:{$desde}:{$hasta}", now()->addSeconds(60), fn () => Invoice::query()
            ->whereNotNull('cae')
            ->where('issue_date', '>=', $desde)
            ->where('issue_date', '<', Carbon::parse($hasta)->addDay()->toDateString())
            ->with('client', 'items')
            ->orderBy('issue_date')
            ->orderBy('punto_venta')
            ->orderBy('tipo_comprobante')
            ->orderBy('numero_comprobante_afip')
            ->get()
            ->map(fn (Invoice $invoice) => self::fromInvoice($invoice)->toArray())
            ->all());

        return collect($rows)->map(fn (array $row) => LibroIvaRow::fromArray($row));
    }

    /**
     * @return Collection<int, LibroIvaRow>
     */
    public static function compras(string $desde, string $hasta): Collection
    {
        // MEJORA: mismo criterio de caché e índice que ventas() de acá arriba.
        //
        // MEJORA: faltaba eager-cargar 'taxes' - fromPurchase() lee
        // $purchase->total, que Purchase::total() calcula sumando
        // percepcionesTotal() (-> $this->taxes->sum(...)), así que sin este
        // with() se disparaba una query extra POR CADA compra del período
        // al armar el Libro IVA Compras (y en sus 4 exports).
        $rows = Cache::remember("libro-iva:compras:{$desde}:{$hasta}", now()->addSeconds(60), fn () => Purchase::query()
            ->whereNot('status', 'draft')
            ->whereNotNull('tipo_comprobante')
            ->where('issue_date', '>=', $desde)
            ->where('issue_date', '<', Carbon::parse($hasta)->addDay()->toDateString())
            ->with('provider', 'items', 'taxes')
            ->orderBy('issue_date')
            ->orderBy('punto_venta')
            ->orderBy('tipo_comprobante')
            ->orderBy('numero_comprobante')
            ->get()
            ->map(fn (Purchase $purchase) => self::fromPurchase($purchase)->toArray())
            ->all());

        return collect($rows)->map(fn (array $row) => LibroIvaRow::fromArray($row));
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
            ->flatMap(fn (LibroIvaRow $row) => $row->alicuotas)
            ->groupBy(fn (LibroIvaAlicuota $a) => number_format($a->tasa, 2, '.', ''))
            ->map(fn (Collection $group, string $tasa) => [
                'tasa' => (float) $tasa,
                'netoGravado' => $group->sum(fn (LibroIvaAlicuota $a) => $a->netoGravado),
                'iva' => $group->sum(fn (LibroIvaAlicuota $a) => $a->ivaLiquidado),
            ])
            ->sortBy('tasa')
            ->values();
    }

    private static function fromInvoice(Invoice $invoice): LibroIvaRow
    {
        $alicuotas = $invoice->ivaPorAlicuota()
            ->map(fn (array $a) => new LibroIvaAlicuota($a['tasa'], (float) $a['base'], (float) $a['iva']))
            ->all();

        return new LibroIvaRow(
            fecha: $invoice->issue_date,
            tipoComprobante: $invoice->tipo_comprobante,
            puntoVenta: $invoice->punto_venta,
            numeroComprobante: $invoice->numero_comprobante_afip,
            codigoDocumento: $invoice->client->tipo_documento->afipCode(),
            numeroDocumento: $invoice->client->tax_id ?: '0',
            denominacion: $invoice->client->name,
            importeTotal: (float) $invoice->total,
            importeExento: (float) $invoice->neto_exento,
            alicuotas: $alicuotas,
            // "E" (exento) solo si el comprobante no tiene ninguna alícuota gravada.
            codigoOperacion: $alicuotas === [] ? 'E' : '',
        );
    }

    private static function fromPurchase(Purchase $purchase): LibroIvaRow
    {
        // Las compras se cargan con una sola alícuota (el comprobante del
        // proveedor), a diferencia de las ventas que la desglosan por ítem.
        //
        // Una compra "sin detalle" (ver Purchases\Create) no tiene ítems de
        // los que derivar subtotal/IVA — su total sale de manual_total. Sin
        // desglose de alícuota disponible, se asienta entero como exento en
        // vez de mostrar $0 gravado con un total no-cero (que no cerraría).
        $tasa = (float) $purchase->tax_rate;
        $exento = $purchase->sin_detalle || $tasa <= 0.0;

        $alicuotas = ($exento || $purchase->sin_detalle)
            ? []
            : [new LibroIvaAlicuota($tasa, (float) $purchase->subtotal, (float) $purchase->tax_amount)];

        return new LibroIvaRow(
            fecha: $purchase->issue_date,
            tipoComprobante: $purchase->tipo_comprobante,
            puntoVenta: $purchase->punto_venta,
            numeroComprobante: $purchase->numero_comprobante,
            codigoDocumento: $purchase->provider->tipo_documento->afipCode(),
            numeroDocumento: $purchase->provider->tax_id ?: '0',
            denominacion: $purchase->provider->name,
            importeTotal: (float) $purchase->total,
            importeExento: $exento ? ($purchase->sin_detalle ? (float) $purchase->total : (float) $purchase->subtotal) : 0.0,
            alicuotas: $alicuotas,
            codigoOperacion: $exento ? 'E' : '',
        );
    }
}
