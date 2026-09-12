<?php

namespace App\Livewire\Purchases;

use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\PurchaseItem;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Suggestions extends Component
{
    private const LOOKBACK_DAYS = 30;

    private const COVERAGE_DAYS = 14;

    public function render()
    {
        $since = now()->subDays(self::LOOKBACK_DAYS);

        $soldQuantities = InvoiceItem::query()
            ->whereNotNull('product_id')
            ->whereHas('invoice', fn ($q) => $q->whereNot('status', 'draft')->whereDate('issue_date', '>=', $since))
            ->selectRaw('product_id, SUM(quantity) as total_qty')
            ->groupBy('product_id')
            ->pluck('total_qty', 'product_id');

        $products = Product::whereNotNull('min_stock')->with('category')->get();

        // Último ítem de compra por producto sin traer el historial entero:
        // se resuelve primero el id más reciente por product_id (agregado en
        // SQL) y recién ahí se cargan esas filas puntuales con su relación.
        $lastPurchaseItemIds = PurchaseItem::query()
            ->whereIn('product_id', $products->pluck('id'))
            ->selectRaw('MAX(id) as id')
            ->groupBy('product_id')
            ->pluck('id');

        $lastProviderByProduct = PurchaseItem::query()
            ->whereIn('id', $lastPurchaseItemIds)
            ->with('purchase.provider')
            ->get()
            ->keyBy('product_id');

        $suggestions = $products
            ->map(function (Product $product) use ($soldQuantities, $lastProviderByProduct) {
                $sold = (float) ($soldQuantities[$product->id] ?? 0);
                $dailyAvg = $sold / self::LOOKBACK_DAYS;
                $suggestedQty = max(0, (int) ceil($dailyAvg * self::COVERAGE_DAYS - $product->stock));

                if ($product->stock < $product->min_stock) {
                    $suggestedQty = max($suggestedQty, $product->min_stock - $product->stock);
                }

                return [
                    'product' => $product,
                    'soldQty' => $sold,
                    'dailyAvg' => $dailyAvg,
                    'suggestedQty' => $suggestedQty,
                    'lastProvider' => $lastProviderByProduct->get($product->id)?->purchase?->provider?->name,
                ];
            })
            ->filter(fn (array $row) => $row['suggestedQty'] > 0)
            ->sortByDesc('soldQty')
            ->values();

        return view('livewire.purchases.suggestions', [
            'suggestions' => $suggestions,
            'lookbackDays' => self::LOOKBACK_DAYS,
            'coverageDays' => self::COVERAGE_DAYS,
        ]);
    }
}
