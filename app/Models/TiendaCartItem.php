<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Línea del carrito de un Customer logueado — ver App\Support\TiendaCart. */
#[Fillable(['customer_id', 'product_id', 'quantity'])]
class TiendaCartItem extends Model
{
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
