<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\Sucursal;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Genera el número interno de un comprobante con formato tipo AFIP/Tango:
 * PPPP-NNNNNNNN (punto de venta de 4 dígitos + correlativo de 8). El
 * correlativo es independiente por punto de venta Y por tipo de comprobante,
 * así que Factura B, Remito X, Nota de crédito, etc., llevan cada uno su
 * propia serie dentro del mismo punto de venta.
 *
 * Centralizado acá para que todos los flujos que crean facturas (alta manual,
 * POS, nota de crédito, conversión de presupuesto, Tiendanube) numeren igual.
 */
class InvoiceNumberGenerator
{
    /**
     * Ejecuta $callback (que debe llamar a next() con el mismo $tipoInterno
     * y crear el comprobante) serializado por punto de venta + tipo. next()
     * por sí solo solo lee el último número con un SELECT plano: sin este
     * lock, dos altas simultáneas del mismo tipo (dos cajas del POS a la
     * vez, por ejemplo) pueden calcular el mismo próximo número y una de
     * las dos se pierde al violar el índice único (tipo, number). Mismo
     * mecanismo que ya usa InvoiceCaeEmitter para la numeración AFIP.
     */
    /**
     * $puntoVentaNumero: qué punto de venta usar, cuando ya se conoce de
     * antemano (elegido a mano en el formulario, o heredado de un
     * comprobante original). Si no se pasa, se resuelve al punto de venta
     * por defecto de $sucursalId (o de la sucursal activa si tampoco se
     * pasa esa) — correcto para una venta nueva sin selector (sucursal con
     * un solo punto de venta).
     */
    public static function withLock(string $tipoInterno, Closure $callback, ?int $sucursalId = null, ?int $puntoVentaNumero = null): mixed
    {
        $pv = self::formatPv($puntoVentaNumero ?? self::resolveDefaultPuntoVenta($sucursalId));

        return Cache::lock("invoice-number:{$pv}:{$tipoInterno}", 10)->block(10, $callback);
    }

    public static function next(string $tipoInterno, ?int $sucursalId = null, ?int $puntoVentaNumero = null): string
    {
        $pv = self::formatPv($puntoVentaNumero ?? self::resolveDefaultPuntoVenta($sucursalId));

        // Último correlativo de esta serie (mismo punto de venta y tipo).
        $last = Invoice::where('tipo_comprobante_interno', $tipoInterno)
            ->where('number', 'like', $pv.'-%')
            ->orderByDesc('id')
            ->value('number');

        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return $pv.'-'.str_pad((string) $seq, 8, '0', STR_PAD_LEFT);
    }

    /**
     * Punto de venta por defecto a 4 dígitos, para cuando no se eligió
     * ninguno a mano: el primero (activo) de la sucursal indicada, o "0001"
     * si esa sucursal no tiene ningún punto de venta cargado todavía.
     */
    public static function puntoVenta(?int $sucursalId = null): string
    {
        return self::formatPv(self::resolveDefaultPuntoVenta($sucursalId));
    }

    private static function resolveDefaultPuntoVenta(?int $sucursalId): int
    {
        $sucursalId ??= CurrentSucursal::id();

        return ($sucursalId ? Sucursal::find($sucursalId)?->puntoVentaPorDefecto()?->numero : null) ?? 1;
    }

    private static function formatPv(int $pv): string
    {
        return str_pad((string) $pv, 4, '0', STR_PAD_LEFT);
    }
}
