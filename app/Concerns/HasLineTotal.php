<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Compartido por InvoiceItem, PurchaseItem y QuoteItem: cantidad x precio
 * unitario, menos el descuento de la línea si el modelo lo maneja
 * (`discount_percent`). PurchaseItem no tiene ese campo, así que ahí el
 * descuento es 0 y el cálculo queda como antes.
 */
trait HasLineTotal
{
    protected function lineTotal(): Attribute
    {
        return Attribute::get(function () {
            $bruto = $this->quantity * $this->unit_price;
            $descuento = (float) ($this->discount_percent ?? 0);

            return $bruto * (1 - $descuento / 100);
        });
    }
}
