<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'buy_qty', 'pay_qty', 'active', 'starts_at', 'ends_at'])]
class PromotionGroup extends Model
{
    protected function casts(): array
    {
        return [
            'buy_qty' => 'integer',
            'pay_qty' => 'integer',
            'active' => 'boolean',
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'promotion_group_product');
    }

    public function scopeActiveNow(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query->where('active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $today))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $today));
    }

    /** Etiqueta corta para el POS (ej. "3x2 familia"). */
    public function shortLabel(): string
    {
        return "{$this->buy_qty}x{$this->pay_qty} familia";
    }
}
