<?php

namespace App\Support\Import;

/**
 * Lógica común para las clases "campos de import" (una por pantalla:
 * productos, clientes, proveedores, etc.). La clase que usa este trait debe
 * definir:
 *
 * - `const FIELDS`: array<string, array{label: string, required: bool, aliases: string[]}>
 * - `const PRIORITY`: string[] — orden de resolución para sugerir(), campos
 *   más específicos antes que los genéricos que podrían matchear el mismo
 *   texto (ej. "stock mínimo" antes que "stock").
 */
trait FieldSetHelpers
{
    /** @return array<string, array{label: string, required: bool, aliases: string[]}> */
    public static function fields(): array
    {
        return self::FIELDS;
    }

    /** @return array<string, string> clave => etiqueta, en el orden que se muestra en la pantalla de mapeo. */
    public static function labels(): array
    {
        return collect(self::FIELDS)->map(fn ($f) => $f['label'])->all();
    }

    public static function esRequerido(string $campo): bool
    {
        return self::FIELDS[$campo]['required'] ?? false;
    }

    /**
     * Dado el listado de cabeceras del Excel (en orden, índice = columna),
     * sugiere qué columna corresponde a cada campo del sistema, buscando
     * coincidencias de texto. Una columna ya asignada no se reutiliza para
     * otro campo.
     *
     * @param  array<int, string>  $headers
     * @return array<string, int|null> campo => índice de columna (o null si no se encontró)
     */
    public static function sugerir(array $headers): array
    {
        $normalizados = array_map(fn ($h) => mb_strtolower(trim((string) $h)), $headers);
        $usados = [];
        $sugerencia = array_fill_keys(array_keys(self::FIELDS), null);

        foreach (self::PRIORITY as $campo) {
            foreach (self::FIELDS[$campo]['aliases'] as $alias) {
                foreach ($normalizados as $indice => $header) {
                    if (in_array($indice, $usados, true) || $header === '') {
                        continue;
                    }

                    if (str_contains($header, $alias)) {
                        $sugerencia[$campo] = $indice;
                        $usados[] = $indice;

                        continue 3;
                    }
                }
            }
        }

        return $sugerencia;
    }
}
