<?php

if (! function_exists('money')) {
    /**
     * Formatea un monto en pesos argentinos: punto de miles, coma decimal
     * (ej. 1.234,56). Usar siempre para plata en vez de number_format($x, 2)
     * directo, que da formato US (1,234.56) — ya hubo un caso real donde
     * la misma pantalla (Cobranzas) mostraba el mismo monto en dos formatos
     * distintos según qué código lo armaba.
     */
    function money(int|float|string $value): string
    {
        return number_format((float) $value, 2, ',', '.');
    }
}
