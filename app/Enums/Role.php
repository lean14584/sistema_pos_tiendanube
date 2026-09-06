<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Encargado = 'encargado';
    case Vendedor = 'vendedor';
    case Cajero = 'cajero';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Encargado => 'Encargado de sucursal',
            self::Vendedor => 'Vendedor',
            self::Cajero => 'Cajero',
        };
    }
}
