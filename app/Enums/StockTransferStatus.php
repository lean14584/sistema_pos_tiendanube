<?php

namespace App\Enums;

enum StockTransferStatus: string
{
    case Pendiente = 'pendiente';
    case Recibido = 'recibido';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente de recepción',
            self::Recibido => 'Recibido',
        };
    }

    public function colorClasses(): string
    {
        return match ($this) {
            self::Pendiente => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-500/20',
            self::Recibido => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/20',
        };
    }
}
