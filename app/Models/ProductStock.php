<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stock de un producto en una sucursal puntual. `products.stock` sigue
 * existiendo como agregado (suma de todas las filas de acá para ese
 * producto), mantenido por StockAdjuster — no escribir stock directo en
 * Product sin pasar por ahí.
 */
#[Fillable(['product_id', 'sucursal_id', 'stock'])]
class ProductStock extends Model
{
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }
}
