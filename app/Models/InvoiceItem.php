<?php

namespace App\Models;

use App\Concerns\HasLineTotal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['invoice_id', 'product_id', 'description', 'quantity', 'unit_price', 'iva_rate'])]
class InvoiceItem extends Model
{
    use HasLineTotal;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'iva_rate' => 'decimal:2',
        ];
    }

    /**
     * Alícuota efectiva del ítem: la propia si está cargada, o la de la
     * factura como fallback (comprobantes viejos / importados sin alícuota
     * por ítem).
     */
    protected function ivaRateEfectiva(): Attribute
    {
        return Attribute::get(fn () => $this->iva_rate !== null
            ? (float) $this->iva_rate
            : (float) ($this->invoice?->tax_rate ?? 0));
    }

    protected function ivaAmount(): Attribute
    {
        return Attribute::get(fn () => $this->line_total * ($this->iva_rate_efectiva / 100));
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
