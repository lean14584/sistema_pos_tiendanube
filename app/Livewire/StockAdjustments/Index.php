<?php

namespace App\Livewire\StockAdjustments;

use App\Enums\StockAdjustmentReason;
use App\Models\Product;
use App\Models\StockAdjustment;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $product_id = '';

    public string $new_stock = '';

    public string $reason = 'conteo_fisico';

    public string $notes = '';

    #[Url]
    public string $filterProduct = '';

    #[Url]
    public string $desde = '';

    #[Url]
    public string $hasta = '';

    public function updating(string $name): void
    {
        if (in_array($name, ['filterProduct', 'desde', 'hasta'], true)) {
            $this->resetPage();
        }
    }

    /** Precarga el stock actual del producto elegido, para partir de un valor real. */
    public function updatedProductId(): void
    {
        $product = $this->product_id !== '' ? Product::find($this->product_id) : null;
        $this->new_stock = $product ? (string) $product->stock : '';
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

        $product = Product::findOrFail($this->product_id);
        $newStock = (int) $this->new_stock;
        $previous = $product->stock;

        DB::transaction(function () use ($product, $newStock, $previous) {
            $product->update(['stock' => $newStock]);

            StockAdjustment::create([
                'product_id' => $product->id,
                'user_id' => auth()->id(),
                'previous_stock' => $previous,
                'new_stock' => $newStock,
                'reason' => $this->reason,
                'notes' => $this->notes ?: null,
            ]);
        });

        session()->flash('status', "Stock de \"{$product->name}\" ajustado de {$previous} a {$newStock}.");
        $this->reset(['product_id', 'new_stock', 'notes']);
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
            'products' => Product::orderBy('name')->get(),
            'reasons' => StockAdjustmentReason::cases(),
        ]);
    }
}
