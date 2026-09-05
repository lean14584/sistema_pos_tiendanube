<?php

namespace App\Support;

use App\Models\CompanySettings;
use App\Models\Invoice;
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
    public static function withLock(string $tipoInterno, Closure $callback): mixed
    {
        $pv = self::puntoVenta();

        return Cache::lock("invoice-number:{$pv}:{$tipoInterno}", 10)->block(10, $callback);
    }

    public static function next(string $tipoInterno): string
    {
        $pv = self::puntoVenta();

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

    /** Punto de venta de la empresa, a 4 dígitos (cae a 0001 si no hay). */
    public static function puntoVenta(): string
    {
        $pv = (int) (CompanySettings::current()->punto_venta ?: 1);

        return str_pad((string) $pv, 4, '0', STR_PAD_LEFT);
    }
}
