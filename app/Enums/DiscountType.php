<?php

namespace App\Enums;

enum DiscountType: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Porcentaje (%)',
            self::Fixed => 'Monto fijo ($)',
        };
    }
}
