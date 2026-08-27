<?php

namespace App\Enums;

enum StockAdjustmentReason: string
{
    case Rotura = 'rotura';
    case Vencimiento = 'vencimiento';
    case ConteoFisico = 'conteo_fisico';
    case MermaRobo = 'merma_robo';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Rotura => 'Rotura',
            self::Vencimiento => 'Vencimiento',
            self::ConteoFisico => 'Conteo físico',
            self::MermaRobo => 'Merma / robo',
            self::Otro => 'Otro',
        };
    }
}
