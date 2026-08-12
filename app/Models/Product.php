<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Observers\ProductObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(ProductObserver::class)]
#[Fillable(['category_id', 'name', 'sku', 'price', 'iva_rate', 'cost_price', 'stock', 'min_stock', 'description', 'image_path', 'tiendanube_product_id', 'tiendanube_variant_id'])]
class Product extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'iva_rate' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'stock' => 'integer',
            'min_stock' => 'integer',
        ];
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereNotNull('min_stock')->whereColumn('stock', '<', 'min_stock');
    }

    /**
     * El conteo de stock bajo se pide en el sidebar (todas las páginas, vía
     * layouts.partials.sidebar) y de nuevo en el Dashboard cuando esa es la
     * página actual — sin esto, visitar el dashboard corre la misma query
     * dos veces en el mismo request. `once()` memoiza por sitio de llamada,
     * así que hay que centralizar la llamada acá para que ambos puntos
     * compartan el resultado.
     */
    public static function lowStockCountCached(): int
    {
        return once(fn () => self::lowStock()->count());
    }

    /** URL pública de la foto del producto, o null si no tiene. */
    public function imageUrl(): ?string
    {
        return $this->image_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->image_path) : null;
    }

    /**
     * Precio de este producto según una lista (ajuste porcentual sobre el
     * precio base). Sin lista, devuelve el precio base.
     */
    public function priceForList(?PriceList $list): float
    {
        $ajuste = $list?->adjustment_percent ?? 0;

        return round((float) $this->price * (1 + (float) $ajuste / 100), 2);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function quoteItems(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
    }

    protected function marginAlert(): Attribute
    {
        return Attribute::get(fn () => $this->cost_price !== null && $this->price < $this->cost_price);
    }

    protected function stockAlert(): Attribute
    {
        return Attribute::get(fn () => $this->min_stock !== null && $this->stock < $this->min_stock);
    }
}
