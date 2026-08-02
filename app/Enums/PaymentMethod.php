<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Efectivo = 'efectivo';
    case Transferencia = 'transferencia';
    case Tarjeta = 'tarjeta';
    case Cheque = 'cheque';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Efectivo => 'Efectivo',
            self::Transferencia => 'Transferencia',
            self::Tarjeta => 'Tarjeta',
            self::Cheque => 'Cheque',
            self::Otro => 'Otro',
        };
    }
}
