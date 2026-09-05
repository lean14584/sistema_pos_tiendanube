<?php

namespace App\Livewire\StockAdjustments;

use App\Enums\StockAdjustmentReason;
use App\Models\Product;
use App\Models\StockAdjustment;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $product_id = '';

    public string $productQuery = '';

    public string $new_stock = '';

    /** Stock del producto al momento de elegirlo, para poder aplicar el ajuste como delta (ver save()). */
    public string $baseline_stock = '';

    public string $reason = 'conteo_fisico';

    public string $notes = '';

    #[Url]
    public string $filterProduct = '';

    public string $filterProductQuery = '';

    #[Url]
    public string $desde = '';

    #[Url]
    public string $hasta = '';

    public function updating(string $name): void
    {
        if (in_array($name, ['desde', 'hasta'], true)) {
            $this->resetPage();
        }
    }

    /** Precarga el stock actual del producto elegido, para partir de un valor real. */
    public function updatedProductId(): void
    {
        $product = $this->product_id !== '' ? Product::find($this->product_id) : null;
        $this->new_stock = $product ? (string) $product->stock : '';
        $this->baseline_stock = $this->new_stock;
    }

    #[Computed]
    public function productResults()
    {
        $term = trim($this->productQuery);

        if ($term === '') {
            return collect();
        }

        return Product::where('name', 'like', "%{$term}%")
            ->orWhere('sku', 'like', "%{$term}%")
            ->limit(8)
            ->get();
    }

    public function selectProduct(int $productId): void
    {
        $this->productQuery = '';
        $this->product_id = (string) $productId;
        $this->updatedProductId();
    }

    #[Computed]
    public function filterProductResults()
    {
        $term = trim($this->filterProductQuery);

        if ($term === '') {
            return collect();
        }

        return Product::where('name', 'like', "%{$term}%")
            ->orWhere('sku', 'like', "%{$term}%")
            ->limit(8)
            ->get();
    }

    public function selectFilterProduct(int $productId): void
    {
        $this->filterProductQuery = '';
        $this->filterProduct = (string) $productId;
        $this->resetPage();
    }

    public function clearFilterProduct(): void
    {
        $this->filterProduct = '';
        $this->filterProductQuery = '';
        $this->resetPage();
    }

    protected function rules(): array
    {
        return [
            'product_id' => ['required', 'exists:products,id'],
            'new_stock' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'in:'.implode(',', array_column(StockAdjustmentReason::cases(), 'value'))],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function save(): void
    {
        $this->validate();

        // El usuario tipea el stock final que contó, relativo al valor que
        // vio al elegir el producto (baseline_stock). Aplicamos la diferencia
        // como delta atómico (increment/decrement), no como un pisado de
        // valor absoluto: si una venta descontó stock mientras esta pantalla
        // estaba abierta, ese descuento no se pierde bajo el ajuste.
        $delta = (int) $this->new_stock - (int) $this->baseline_stock;

        [$product, $previous, $newStock] = DB::transaction(function () use ($delta) {
            $product = Product::whereKey($this->product_id)->lockForUpdate()->firstOrFail();
            $previous = $product->stock;

            $product->increment('stock', $delta);

            StockAdjustment::create([
                'product_id' => $product->id,
                'user_id' => auth()->id(),
                'previous_stock' => $previous,
                'new_stock' => $previous + $delta,
                'reason' => $this->reason,
                'notes' => $this->notes ?: null,
            ]);

            return [$product, $previous, $previous + $delta];
        });

        session()->flash('status', "Stock de \"{$product->name}\" ajustado de {$previous} a {$newStock}.");
        $this->reset(['product_id', 'productQuery', 'new_stock', 'baseline_stock', 'notes']);
        $this->reason = 'conteo_fisico';
    }

    public function render()
    {
        $adjustments = StockAdjustment::with(['product', 'user'])
            ->when($this->filterProduct !== '', fn ($q) => $q->where('product_id', $this->filterProduct))
            ->when($this->desde !== '', fn ($q) => $q->whereDate('created_at', '>=', $this->desde))
            ->when($this->hasta !== '', fn ($q) => $q->whereDate('created_at', '<=', $this->hasta))
            ->latest()
            ->paginate(20);

        return view('livewire.stock-adjustments.index', [
            'adjustments' => $adjustments,
            'selectedProductName' => $this->product_id !== '' ? Product::find($this->product_id)?->name : null,
            'filterProductName' => $this->filterProduct !== '' ? Product::find($this->filterProduct)?->name : null,
            'reasons' => StockAdjustmentReason::cases(),
        ]);
    }
}
