<?php

namespace App\Enums;

enum TipoComprobanteInterno: string
{
    case RemitoX = 'remito_x';
    case FacturaB = 'factura_b';
    case FacturaA = 'factura_a';
    case Devolucion = 'devolucion';
    case NotaCreditoA = 'nota_credito_a';
    case NotaCreditoB = 'nota_credito_b';

    public function label(): string
    {
        return match ($this) {
            self::RemitoX => 'Remito X',
            self::FacturaB => 'Factura B',
            self::FacturaA => 'Factura A',
            self::Devolucion => 'Devolución',
            self::NotaCreditoA => 'Nota de Crédito A',
            self::NotaCreditoB => 'Nota de Crédito B',
        };
    }

    /**
     * Los 4 tipos que se pueden elegir libremente desde el switch de
     * Invoices/Create y Edit. Las Notas de Crédito no están acá — se crean
     * solo desde la factura original que acreditan (ver NotasCredito/Create).
     */
    public static function seleccionablesEnFactura(): array
    {
        return [self::RemitoX, self::FacturaB, self::FacturaA, self::Devolucion];
    }

    /**
     * -1: descuenta stock (venta o remito). 1: repone stock (devolución o
     * nota de crédito). Para Notas de Crédito, quien llama todavía tiene
     * que multiplicar esto por el checkbox `afecta_stock` de la factura.
     */
    public function stockSign(): int
    {
        return $this === self::FacturaA || $this === self::FacturaB || $this === self::RemitoX ? -1 : 1;
    }

    /**
     * Factura A/B y Nota de Crédito A/B se emiten a AFIP — Remito X y
     * Devolución son puramente internos.
     */
    public function esFiscal(): bool
    {
        return in_array($this, [self::FacturaA, self::FacturaB, self::NotaCreditoA, self::NotaCreditoB], true);
    }

    public function aTipoComprobante(): ?TipoComprobante
    {
        return match ($this) {
            self::FacturaA => TipoComprobante::FacturaA,
            self::FacturaB => TipoComprobante::FacturaB,
            self::NotaCreditoA => TipoComprobante::NotaCreditoA,
            self::NotaCreditoB => TipoComprobante::NotaCreditoB,
            default => null,
        };
    }

    public function esNotaCredito(): bool
    {
        return $this === self::NotaCreditoA || $this === self::NotaCreditoB;
    }
}
