<?php

namespace App\Support\Afip;

use App\Enums\CondicionIva;
use App\Enums\TipoComprobante;
use App\Exceptions\Afip\AfipValidationException;
use DomainException;

/**
 * Único punto de la app que decide qué tipo de comprobante (Factura A/B/C)
 * y qué CondicionIVAReceptorId corresponde. No hardcodear estos valores en
 * ningún otro lugar — ArcaService.php (proyecto hermano posOfflineDos)
 * hardcodeaba CbteTipo=6 y CondicionIVAReceptorId=5 siempre, con un método
 * de mapeo que existía pero nunca se llamaba desde el flujo real.
 */
final class ComprobanteResolver
{
    /**
     * Catálogo de AFIP para CondicionIVAReceptorId (WSFEv1, RG 5616).
     * Valores de referencia — confirmar contra homologación llamando a
     * FEParamGetCondicionIvaReceptor antes de emitir en producción.
     */
    private const CONDICION_IVA_RECEPTOR_ID = [
        'responsable_inscripto' => 1,
        'exento' => 4,
        'consumidor_final' => 5,
        'monotributista' => 6,
    ];

    public static function tipoComprobante(CondicionIva $emisor, CondicionIva $receptor): TipoComprobante
    {
        return match ($emisor) {
            CondicionIva::Monotributista, CondicionIva::Exento => TipoComprobante::FacturaC,
            CondicionIva::ResponsableInscripto => $receptor === CondicionIva::ResponsableInscripto
                ? TipoComprobante::FacturaA
                : TipoComprobante::FacturaB,
            CondicionIva::ConsumidorFinal => throw new DomainException(
                'La empresa emisora no puede facturar como Consumidor Final.'
            ),
        };
    }

    public static function condicionIvaReceptorId(CondicionIva $receptor): int
    {
        return self::CONDICION_IVA_RECEPTOR_ID[$receptor->value];
    }

    /**
     * Un Monotributista o Exento no puede emitir nada de familia A o B
     * (Factura, Nota de Crédito o Débito) bajo ningún concepto — es una
     * regla dura de AFIP, no una preferencia. Se usa cuando el tipo de
     * comprobante viene forzado (switch de Invoices/Create, o derivado de
     * la factura original al emitir una Nota de Crédito) en vez de
     * calculado por tipoComprobante().
     */
    public static function assertEmisorPuedeForzar(CondicionIva $emisor, TipoComprobante $tipo): void
    {
        $esAoB = in_array($tipo->family(), ['A', 'B'], true);

        if ($esAoB && $emisor !== CondicionIva::ResponsableInscripto) {
            throw new AfipValidationException("Una empresa {$emisor->label()} no puede emitir {$tipo->label()}.");
        }
    }
}
