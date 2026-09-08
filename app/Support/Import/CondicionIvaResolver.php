<?php

namespace App\Support\Import;

use App\Enums\CondicionIva;

/** Interpreta el texto libre de "condición IVA" que trae un Excel de otro sistema. */
class CondicionIvaResolver
{
    public static function desdeTexto(?string $texto): CondicionIva
    {
        $normalizado = strtr(mb_strtolower(trim((string) $texto)), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);

        return match (true) {
            str_contains($normalizado, 'inscripto') => CondicionIva::ResponsableInscripto,
            str_contains($normalizado, 'monotribut') => CondicionIva::Monotributista,
            str_contains($normalizado, 'exento') => CondicionIva::Exento,
            default => CondicionIva::ConsumidorFinal,
        };
    }
}
