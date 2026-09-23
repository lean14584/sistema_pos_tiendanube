<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\HasBillingTotals;
use App\Concerns\HasOverdueStatus;
use App\Enums\InvoiceStatus;
use App\Enums\TipoComprobante;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'number', 'provider_id', 'sucursal_id', 'issue_date', 'due_date', 'tax_rate', 'notes', 'status',
    'tipo_comprobante', 'punto_venta', 'numero_comprobante',
    'sin_detalle', 'manual_total', 'remito_number',
])]
class Purchase extends Model
{
    use Auditable, HasBillingTotals, HasOverdueStatus;

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'tax_rate' => 'decimal:2',
            'status' => InvoiceStatus::class,
            'tipo_comprobante' => TipoComprobante::class,
            'sin_detalle' => 'boolean',
            'manual_total' => 'decimal:2',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PurchasePayment::class);
    }

    /**
     * Impuestos y percepciones cargados de la factura del proveedor
     * (Percepción IVA, IIBB, Ganancias, impuestos internos, etc.).
     */
    public function taxes(): HasMany
    {
        return $this->hasMany(PurchaseTax::class);
    }

    /** Suma de todas las percepciones / impuestos extra de la compra. */
    protected function percepcionesTotal(): Attribute
    {
        return Attribute::get(fn () => $this->taxes->sum(fn (PurchaseTax $t) => (float) $t->amount));
    }

    /**
     * Total de la compra: subtotal + IVA + percepciones (sobrescribe el
     * total de HasBillingTotals para sumar los impuestos extra). Si la
     * compra se cargó "sin detalle" (sin productos, ver Purchases\Create),
     * no hay ítems de los que derivar subtotal/IVA — el total es el que se
     * tipeó a mano.
     */
    protected function total(): Attribute
    {
        return Attribute::get(fn () => $this->sin_detalle
            ? (float) $this->manual_total
            : $this->subtotal + $this->tax_amount + $this->percepciones_total);
    }
}
