<?php

namespace App\Enums;

enum PromotionType: string
{
    case Nxm = 'nxm';
    case Segunda = 'segunda';
    case Cantidad = 'cantidad';

    public function label(): string
    {
        return match ($this) {
            self::Nxm => 'Nx M (2x1, 3x2...)',
            self::Segunda => '2da unidad con descuento',
            self::Cantidad => 'Descuento por cantidad',
        };
    }
}
