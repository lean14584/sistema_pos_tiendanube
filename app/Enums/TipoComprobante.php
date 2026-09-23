<?php

namespace App\Enums;

enum TipoComprobante: int
{
    case FacturaA = 1;
    case NotaDebitoA = 2;
    case NotaCreditoA = 3;
    case FacturaB = 6;
    case NotaDebitoB = 7;
    case NotaCreditoB = 8;
    case FacturaC = 11;
    case NotaDebitoC = 12;
    case NotaCreditoC = 13;

    /**
     * Solo seleccionable en Compras (ver Purchases\Create/Edit) — el
     * proveedor entregó la mercadería con remito pero todavía no facturó.
     * Código AFIP 91 (tabla de comprobantes). No es un comprobante fiscal
     * con crédito de IVA: LibroIvaCalculator::compras() lo excluye del
     * Libro IVA Compras a propósito.
     */
    case Remito = 91;

    public function label(): string
    {
        return match ($this) {
            self::FacturaA => 'Factura A',
            self::NotaDebitoA => 'Nota de Débito A',
            self::NotaCreditoA => 'Nota de Crédito A',
            self::FacturaB => 'Factura B',
            self::NotaDebitoB => 'Nota de Débito B',
            self::NotaCreditoB => 'Nota de Crédito B',
            self::FacturaC => 'Factura C',
            self::NotaDebitoC => 'Nota de Débito C',
            self::NotaCreditoC => 'Nota de Crédito C',
            self::Remito => 'Remito',
        };
    }

    /**
     * Letra del comprobante (A/B/C), útil para elegir la Nota de Crédito
     * que corresponde a una factura ya emitida. Solo tiene sentido para
     * comprobantes fiscales de venta (Invoice) — Remito es exclusivo de
     * Purchases y nunca debería llegar acá.
     */
    public function family(): string
    {
        return match ($this) {
            self::FacturaA, self::NotaDebitoA, self::NotaCreditoA => 'A',
            self::FacturaB, self::NotaDebitoB, self::NotaCreditoB => 'B',
            self::FacturaC, self::NotaDebitoC, self::NotaCreditoC => 'C',
            self::Remito => throw new \LogicException('Remito no tiene family(): no es un comprobante fiscal A/B/C, es exclusivo de Compras.'),
        };
    }
}
