<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\PromotionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'type', 'buy_qty', 'pay_qty', 'percent', 'min_qty', 'active', 'starts_at', 'ends_at'])]
class Promotion extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'type' => PromotionType::class,
            'buy_qty' => 'integer',
            'pay_qty' => 'integer',
            'percent' => 'decimal:2',
            'min_qty' => 'integer',
            'active' => 'boolean',
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Promociones activas y dentro de su vigencia (si tiene fechas).
     */
    public function scopeActiveNow(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query->where('active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $today))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $today));
    }

    /** Etiqueta corta para mostrar en el POS (ej. "2x1", "2da -50%", "-15% x10+"). */
    public function shortLabel(): string
    {
        return match ($this->type) {
            PromotionType::Nxm => "{$this->buy_qty}x{$this->pay_qty}",
            PromotionType::Segunda => '2da -'.rtrim(rtrim(number_format((float) $this->percent, 2), '0'), '.').'%',
            PromotionType::Cantidad => '-'.rtrim(rtrim(number_format((float) $this->percent, 2), '0'), '.')."% x{$this->min_qty}+",
        };
    }
}
