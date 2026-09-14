<?php

namespace App\Enums;

enum TipoComprobanteInterno: string
{
    case RemitoX = 'remito_x';
    case FacturaB = 'factura_b';
    case FacturaA = 'factura_a';
    case FacturaC = 'factura_c';
    case Devolucion = 'devolucion';
    case NotaCreditoA = 'nota_credito_a';
    case NotaCreditoB = 'nota_credito_b';
    case NotaCreditoC = 'nota_credito_c';

    public function label(): string
    {
        return match ($this) {
            self::RemitoX => 'Remito X',
            self::FacturaB => 'Factura B',
            self::FacturaA => 'Factura A',
            self::FacturaC => 'Factura C',
            self::Devolucion => 'Devolución',
            self::NotaCreditoA => 'Nota de Crédito A',
            self::NotaCreditoB => 'Nota de Crédito B',
            self::NotaCreditoC => 'Nota de Crédito C',
        };
    }

    /**
     * Los tipos que se pueden elegir libremente desde el switch de
     * Invoices/Create y Edit. Las Notas de Crédito no están acá — se crean
     * solo desde la factura original que acreditan (ver NotasCredito/Create).
     * Factura C queda disponible acá igual que A/B: CompanySettings::
     * tiposComprobanteSeleccionables() es quien filtra según lo que la
     * empresa tiene habilitado (y su condición ante IVA).
     */
    public static function seleccionablesEnFactura(): array
    {
        return [self::RemitoX, self::FacturaB, self::FacturaA, self::FacturaC, self::Devolucion];
    }

    /**
     * -1: descuenta stock (venta o remito). 1: repone stock (devolución o
     * nota de crédito). Para Notas de Crédito, quien llama todavía tiene
     * que multiplicar esto por el checkbox `afecta_stock` de la factura.
     */
    public function stockSign(): int
    {
        return in_array($this, [self::FacturaA, self::FacturaB, self::FacturaC, self::RemitoX], true) ? -1 : 1;
    }

    /**
     * Factura A/B/C y Nota de Crédito A/B/C se emiten a AFIP — Remito X y
     * Devolución son puramente internos.
     */
    public function esFiscal(): bool
    {
        return in_array($this, [
            self::FacturaA, self::FacturaB, self::FacturaC,
            self::NotaCreditoA, self::NotaCreditoB, self::NotaCreditoC,
        ], true);
    }

    /**
     * Los tipos que se emiten a AFIP (los que necesitan CAE).
     *
     * @return array<int, self>
     */
    public static function fiscales(): array
    {
        return array_values(array_filter(self::cases(), fn (self $c) => $c->esFiscal()));
    }

    public function aTipoComprobante(): ?TipoComprobante
    {
        return match ($this) {
            self::FacturaA => TipoComprobante::FacturaA,
            self::FacturaB => TipoComprobante::FacturaB,
            self::FacturaC => TipoComprobante::FacturaC,
            self::NotaCreditoA => TipoComprobante::NotaCreditoA,
            self::NotaCreditoB => TipoComprobante::NotaCreditoB,
            self::NotaCreditoC => TipoComprobante::NotaCreditoC,
            default => null,
        };
    }

    public function esNotaCredito(): bool
    {
        return in_array($this, [self::NotaCreditoA, self::NotaCreditoB, self::NotaCreditoC], true);
    }
}
