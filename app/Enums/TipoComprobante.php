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
        };
    }

    /**
     * Letra del comprobante (A/B/C), útil para elegir la Nota de Crédito
     * que corresponde a una factura ya emitida.
     */
    public function family(): string
    {
        return match ($this) {
            self::FacturaA, self::NotaDebitoA, self::NotaCreditoA => 'A',
            self::FacturaB, self::NotaDebitoB, self::NotaCreditoB => 'B',
            self::FacturaC, self::NotaDebitoC, self::NotaCreditoC => 'C',
        };
    }
}
