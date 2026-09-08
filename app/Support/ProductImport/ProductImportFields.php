<?php

namespace App\Support\ProductImport;

use App\Support\Import\FieldSetHelpers;

/**
 * Campos del sistema que se pueden emparejar con columnas de un Excel al
 * importar/exportar productos. El orden en PRIORITY importa para el
 * auto-sugerido: campos más específicos ("stock mínimo") se resuelven antes
 * que los genéricos que podrían matchear el mismo texto ("stock").
 */
class ProductImportFields
{
    use FieldSetHelpers;

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
}
