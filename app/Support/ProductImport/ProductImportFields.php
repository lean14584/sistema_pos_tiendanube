<?php

namespace App\Support\ProductImport;

/**
 * Campos del sistema que se pueden emparejar con columnas de un Excel al
 * importar/exportar productos. El orden en PRIORITY importa para el
 * auto-sugerido: campos más específicos ("stock mínimo") se resuelven antes
 * que los genéricos que podrían matchear el mismo texto ("stock").
 */
class ProductImportFields
{
    public const FIELDS = [
        'sku' => ['label' => 'SKU / Código', 'required' => false, 'aliases' => ['sku', 'codigo', 'código', 'cod']],
        'category' => ['label' => 'Categoría', 'required' => false, 'aliases' => ['categoria', 'categoría', 'rubro']],
        'min_stock' => ['label' => 'Stock mínimo', 'required' => false, 'aliases' => ['stock minimo', 'stock mínimo', 'minimo', 'mínimo']],
        'cost_price' => ['label' => 'Precio de costo', 'required' => false, 'aliases' => ['precio costo', 'precio de costo', 'costo', 'compra']],
        'iva_rate' => ['label' => 'Alícuota IVA', 'required' => false, 'aliases' => ['iva', 'alicuota', 'alícuota']],
        'price' => ['label' => 'Precio de venta', 'required' => true, 'aliases' => ['precio venta', 'precio de venta', 'precio', 'pvp', 'venta']],
        'stock' => ['label' => 'Stock', 'required' => false, 'aliases' => ['stock', 'cantidad', 'existencia']],
        'description' => ['label' => 'Descripción', 'required' => false, 'aliases' => ['descripcion', 'descripción', 'detalle', 'observaciones']],
        'name' => ['label' => 'Nombre', 'required' => true, 'aliases' => ['nombre', 'producto', 'articulo', 'artículo', 'item', 'ítem']],
    ];

    /** Orden de resolución para el auto-sugerido (específico antes que genérico). */
    private const PRIORITY = ['sku', 'category', 'min_stock', 'cost_price', 'iva_rate', 'price', 'stock', 'description', 'name'];

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
