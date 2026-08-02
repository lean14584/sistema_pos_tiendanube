<?php

namespace App\Enums;

enum CondicionIva: string
{
    case ResponsableInscripto = 'responsable_inscripto';
    case Monotributista = 'monotributista';
    case Exento = 'exento';
    case ConsumidorFinal = 'consumidor_final';

    public function label(): string
    {
        return match ($this) {
            self::ResponsableInscripto => 'Responsable Inscripto',
            self::Monotributista => 'Monotributista',
            self::Exento => 'Exento',
            self::ConsumidorFinal => 'Consumidor Final',
        };
    }
}
