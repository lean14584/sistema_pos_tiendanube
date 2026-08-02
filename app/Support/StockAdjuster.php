<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Collection;

class StockAdjuster
{
    /**
     * Apply a stock delta for a set of purchase items ({product_id, quantity} shaped).
     * $sign is 1 to add stock (purchase created) or -1 to remove it (purchase deleted/reverted).
     */
    public static function apply(iterable $items, int $sign): void
    {
        $deltas = collect($items)
            ->filter(fn ($item) => ! empty($item['product_id'] ?? null))
            ->groupBy('product_id')
            ->map(fn (Collection $group) => $group->sum('quantity') * $sign);

        foreach ($deltas as $productId => $delta) {
            if ($delta !== 0) {
                Product::whereKey($productId)->increment('stock', $delta);
            }
        }
    }
}
