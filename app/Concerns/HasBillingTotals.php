<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Compartido por Invoice, Purchase y Quote: subtotal = suma de line_total de
 * los ítems, impuesto = subtotal x tax_rate%, total = subtotal + impuesto.
 * Requiere que el modelo tenga una relación `items()` (cuyos elementos
 * expongan `line_total`, ver HasLineTotal) y un atributo `tax_rate`.
 */
trait HasBillingTotals
{
    protected function subtotal(): Attribute
    {
        return Attribute::get(fn () => $this->items->sum(fn ($item) => $item->line_total));
    }

    protected function taxAmount(): Attribute
    {
        return Attribute::get(fn () => $this->subtotal * ($this->tax_rate / 100));
    }

    protected function total(): Attribute
    {
        return Attribute::get(fn () => $this->subtotal + $this->tax_amount);
    }
}
