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
    'number', 'provider_id', 'issue_date', 'due_date', 'tax_rate', 'notes', 'status',
    'tipo_comprobante', 'punto_venta', 'numero_comprobante',
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
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
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
     * total de HasBillingTotals para sumar los impuestos extra).
     */
    protected function total(): Attribute
    {
        return Attribute::get(fn () => $this->subtotal + $this->tax_amount + $this->percepciones_total);
    }
}
