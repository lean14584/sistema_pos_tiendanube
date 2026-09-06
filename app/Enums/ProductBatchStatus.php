<?php

namespace App\Enums;

enum ProductBatchStatus: string
{
    case Ok = 'ok';
    case PorVencer = 'por_vencer';
    case Vencido = 'vencido';

    public function label(): string
    {
        return match ($this) {
            self::Ok => 'OK',
            self::PorVencer => 'Por vencer',
            self::Vencido => 'Vencido',
        };
    }

    public function colorClasses(): string
    {
        return match ($this) {
            self::Ok => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/20',
            self::PorVencer => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-500/20',
            self::Vencido => 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/10 dark:text-red-400 dark:ring-red-500/20',
        };
    }
}
