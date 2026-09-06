<?php

namespace App\Models;

use App\Enums\ProductBatchStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Días de anticipación para marcar un lote como "por vencer" (ver ProductBatchStatus). */
#[Fillable(['product_id', 'sucursal_id', 'purchase_id', 'batch_number', 'quantity_received', 'quantity_remaining', 'expiration_date', 'notes', 'written_off_at'])]
class ProductBatch extends Model
{
    const DIAS_ALERTA = 7;

    protected function casts(): array
    {
        return [
            'quantity_received' => 'decimal:2',
            'quantity_remaining' => 'decimal:2',
            'expiration_date' => 'date',
            'written_off_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('quantity_remaining', '>', 0);
    }

    public function scopeExpiringWithin(Builder $query, int $days): Builder
    {
        return $query->whereDate('expiration_date', '<=', now()->addDays($days)->toDateString());
    }

    protected function diasParaVencer(): Attribute
    {
        return Attribute::get(fn () => (int) now()->startOfDay()->diffInDays($this->expiration_date, false));
    }

    protected function status(): Attribute
    {
        return Attribute::get(function () {
            if ($this->dias_para_vencer < 0) {
                return ProductBatchStatus::Vencido;
            }

            return $this->dias_para_vencer <= self::DIAS_ALERTA
                ? ProductBatchStatus::PorVencer
                : ProductBatchStatus::Ok;
        });
    }

    /**
     * Cuántos lotes activos están vencidos o por vencer (para el badge del
     * sidebar) — global, sin recortar por sucursal, igual criterio que
     * Product::lowStockCountCached().
     */
    public static function alertCountCached(): int
    {
        return once(fn () => self::active()->expiringWithin(self::DIAS_ALERTA)->count());
    }
}
