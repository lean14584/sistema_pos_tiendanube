<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Efectivo = 'efectivo';
    case Transferencia = 'transferencia';
    case Tarjeta = 'tarjeta';
    case MercadoPago = 'mercadopago';
    case Cheque = 'cheque';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Efectivo => 'Efectivo',
            self::Transferencia => 'Transferencia',
            self::Tarjeta => 'Tarjeta',
            self::MercadoPago => 'Mercado Pago (QR)',
            self::Cheque => 'Cheque',
            self::Otro => 'Otro',
        };
    }
}
