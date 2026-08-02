<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Pending = 'pendiente';
    case InProgress = 'en_progreso';
    case Completed = 'completada';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::InProgress => 'En progreso',
            self::Completed => 'Completada',
        };
    }

    public function colorClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-gray-100 text-gray-700 ring-gray-500/20 dark:bg-gray-500/10 dark:text-gray-400 dark:ring-gray-500/20',
            self::InProgress => 'bg-blue-50 text-blue-700 ring-blue-600/20 dark:bg-blue-500/10 dark:text-blue-400 dark:ring-blue-500/20',
            self::Completed => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/20',
        };
    }
}
