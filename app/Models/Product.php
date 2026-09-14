<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Observers\ProductObserver;
use App\Support\CurrentSucursal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[ObservedBy(ProductObserver::class)]
#[Fillable(['category_id', 'name', 'sku', 'sold_by_weight', 'price', 'iva_rate', 'cost_price', 'stock', 'min_stock', 'description', 'image_path', 'tiendanube_product_id', 'tiendanube_variant_id'])]
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
            'sold_by_weight' => 'boolean',
        ];
    }

    /**
     * MEJORA: comparaba siempre contra products.stock (la suma de TODAS las
     * sucursales) — en una instalación multisucursal eso da falsos negativos
     * (una sucursal crítica no se marca porque otra tiene de sobra) y falsos
     * positivos (se marca en rojo aunque la sucursal activa esté bien). Por
     * defecto compara contra el stock de la sucursal activa (CurrentSucursal
     * nunca es null en un sistema migrado); con un solo local (o sin sesión,
     * ej. un comando de consola) da exactamente el mismo resultado que antes.
     */
    public function scopeLowStock(Builder $query, ?int $sucursalId = null): Builder
    {
        $sucursalId ??= CurrentSucursal::id();

        if ($sucursalId === null) {
            return $query->whereNotNull('min_stock')->whereColumn('stock', '<', 'min_stock');
        }

        return $query->whereNotNull('min_stock')->whereRaw(
            '(select coalesce(sum(ps.stock), 0) from product_stocks ps where ps.product_id = products.id and ps.sucursal_id = ?) < products.min_stock',
            [$sucursalId]
        );
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
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
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

    public function stockAdjustments(): HasMany
    {
        return $this->hasMany(StockAdjustment::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }

    /** Stock de este producto en una sucursal puntual (0 si nunca se movió ahí). */
    public function stockEnSucursal(?int $sucursalId = null): int
    {
        $sucursalId ??= CurrentSucursal::id();

        if ($sucursalId === null) {
            return 0;
        }

        return (int) ($this->relationLoaded('stocks')
            ? $this->stocks->firstWhere('sucursal_id', $sucursalId)?->stock
            : $this->stocks()->where('sucursal_id', $sucursalId)->value('stock')) ?: 0;
    }

    protected function marginAlert(): Attribute
    {
        return Attribute::get(fn () => $this->cost_price !== null && $this->price < $this->cost_price);
    }

    /** Mismo criterio que scopeLowStock(): compara contra el stock de la sucursal activa, no el total de la empresa. */
    protected function stockAlert(): Attribute
    {
        return Attribute::get(fn () => $this->min_stock !== null && $this->stockEnSucursal() < $this->min_stock);
    }
}
