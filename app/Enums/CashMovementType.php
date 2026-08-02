<?php

namespace App\Enums;

enum CashMovementType: string
{
    case Ingreso = 'ingreso';
    case Egreso = 'egreso';

    public function label(): string
    {
        return match ($this) {
            self::Ingreso => 'Ingreso',
            self::Egreso => 'Egreso',
        };
    }
}
