<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Compartido por InvoiceItem, PurchaseItem y QuoteItem: los tres son
 * cantidad x precio unitario, nada más. Antes estaba copiado y pegado
 * idéntico en los tres modelos.
 */
trait HasLineTotal
{
    protected function lineTotal(): Attribute
    {
        return Attribute::get(fn () => $this->quantity * $this->unit_price);
    }
}
