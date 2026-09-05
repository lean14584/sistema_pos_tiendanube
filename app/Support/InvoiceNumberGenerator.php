<?php

namespace App\Support;

use App\Models\CompanySettings;
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
     * $sucursalId: de qué sucursal es el punto de venta a usar. Si no se
     * pasa, se resuelve a la sucursal activa (CurrentSucursal) — correcto
     * para una venta nueva (se numera en el momento, en la sucursal donde
     * está pasando). Notas de Crédito y "facturar remito" SÍ pasan la
     * sucursal explícita (la del comprobante original), para no numerar con
     * el punto de venta de la sesión de quien los procesa después.
     */
    public static function withLock(string $tipoInterno, Closure $callback, ?int $sucursalId = null): mixed
    {
        $pv = self::puntoVenta($sucursalId);

        return Cache::lock("invoice-number:{$pv}:{$tipoInterno}", 10)->block(10, $callback);
    }

    public static function next(string $tipoInterno, ?int $sucursalId = null): string
    {
        $pv = self::puntoVenta($sucursalId);

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
     * Punto de venta a 4 dígitos: el de la sucursal (propia o activa), o el
     * de la empresa si no hay ninguna sucursal resoluble (cae a 0001 si
     * tampoco hay eso).
     */
    public static function puntoVenta(?int $sucursalId = null): string
    {
        $sucursalId ??= CurrentSucursal::id();

        $pv = ($sucursalId ? Sucursal::find($sucursalId)?->punto_venta : null)
            ?? CompanySettings::current()->punto_venta
            ?? 1;

        return str_pad((string) $pv, 4, '0', STR_PAD_LEFT);
    }
}
