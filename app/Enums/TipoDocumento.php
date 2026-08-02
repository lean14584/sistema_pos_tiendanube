<?php

namespace App\Enums;

enum TipoDocumento: string
{
    case Cuit = 'cuit';
    case Dni = 'dni';
    case SinIdentificar = 'sin_identificar';

    public function label(): string
    {
        return match ($this) {
            self::Cuit => 'CUIT',
            self::Dni => 'DNI',
            self::SinIdentificar => 'Sin identificar',
        };
    }

    /**
     * Código de tipo de documento del catálogo de AFIP (DocTipo).
     */
    public function afipCode(): int
    {
        return match ($this) {
            self::Cuit => 80,
            self::Dni => 96,
            self::SinIdentificar => 99,
        };
    }
}
