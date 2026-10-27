<?php

namespace App\Support;

use App\Enums\AlicuotaIva;
use App\Enums\TipoComprobanteInterno;
use App\Models\Invoice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Convierte un Remito (ver TipoComprobanteInterno::RemitoX, no fiscal) en una
 * Factura real — misma lógica que usaba exclusivamente
 * Livewire\Invoices\FacturarRemito, extraída acá para que también la use el
 * pago online de la tienda (ver Observers\InvoiceObserver) sin duplicar la
 * transacción ni el lock.
 */
class RemitoFacturador
{
    /**
     * @throws RuntimeException si el remito ya fue facturado (doble clic, o
     *                           dos disparadores de pago casi simultáneos).
     */
    public static function facturar(Invoice $remito, TipoComprobanteInterno $tipo): Invoice
    {
        // El chequeo "¿ya se facturó?" y la creación tienen que ser una sola
        // unidad atómica: sin este lock, dos llamadas casi simultáneas (doble
        // clic, o el webhook de MP reintentando) pueden pasar las dos el
        // chequeo y generar dos facturas del mismo remito.
        return Cache::lock("facturar-remito:{$remito->id}", 10)->block(5, function () use ($remito, $tipo) {
            $remito = $remito->fresh();

            if ($remito->facturaGenerada() !== null) {
                throw new RuntimeException('Este remito ya fue facturado.');
            }

            // Punto de venta de la factura resultante = el del remito
            // original (misma sucursal, misma venta), no la de quien dispara
            // la facturación ahora (un webhook no tiene sesión).
            $puntoVentaNumero = $remito->punto_venta
                ?? $remito->sucursal?->puntoVentaPorDefecto()?->numero
                ?? 1;

            return InvoiceNumberGenerator::withLock($tipo->value, fn () => DB::transaction(function () use ($tipo, $remito, $puntoVentaNumero) {
                $factura = Invoice::create([
                    'number' => InvoiceNumberGenerator::next($tipo->value, null, $puntoVentaNumero),
                    'client_id' => $remito->client_id,
                    'sucursal_id' => $remito->sucursal_id,
                    'punto_venta' => $puntoVentaNumero,
                    'tipo_comprobante_interno' => $tipo,
                    'remito_id' => $remito->id,
                    // Se arrastra del remito para que la factura resultante
                    // siga identificable como venta de la tienda online en
                    // Informes/Dashboard y en CashLinker — sin esto, una vez
                    // convertido el remito en factura se perdía la marca de
                    // origen (ver Invoice::esDeTiendaOnline()).
                    'tienda_pedido_id' => $remito->tienda_pedido_id,
                    'issue_date' => now()->toDateString(),
                    'due_date' => now()->addDays(15)->toDateString(),
                    'tax_rate' => 0,
                    // El stock ya lo movió el remito: la factura NO lo vuelve a tocar.
                    'afecta_stock' => false,
                    'status' => 'draft',
                    'notes' => 'Facturación del remito '.$remito->number,
                ]);

                foreach ($remito->items as $item) {
                    $factura->items()->create([
                        'product_id' => $item->product_id,
                        'description' => $item->description,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'discount_percent' => $item->discount_percent,
                        'iva_rate' => AlicuotaIva::normalizar($item->iva_rate_efectiva),
                    ]);
                }

                return $factura;
            }), null, $puntoVentaNumero);
        });
    }
}
