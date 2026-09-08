<?php

namespace App\Support\Import;

use Illuminate\Support\Carbon;

/**
 * Interpreta fechas de un Excel exportado en Argentina (dd/mm/aaaa), donde
 * Carbon::parse() a secas puede confundir día y mes para fechas ambiguas
 * (ej. "01/02/2026" como 1 de febrero en vez de 2 de enero).
 */
class ExcelDateParser
{
    public static function parse(mixed $valor): ?Carbon
    {
        $texto = trim((string) $valor);

        if ($texto === '') {
            return null;
        }

        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{2,4})$#', $texto, $m)) {
            $anio = strlen($m[3]) === 2 ? '20'.$m[3] : $m[3];

            try {
                return Carbon::createFromFormat('d/m/Y', sprintf('%02d/%02d/%04d', $m[1], $m[2], $anio));
            } catch (\Throwable) {
                return null;
            }
        }

        try {
            return Carbon::parse($texto);
        } catch (\Throwable) {
            return null;
        }
    }
}
