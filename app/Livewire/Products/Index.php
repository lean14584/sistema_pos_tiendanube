<?php

namespace App\Livewire\Products;

use App\Livewire\Concerns\ShowsToasts;
use App\Models\Product;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    use ShowsToasts;

    #[Url]
    public string $query = '';

    #[Url]
    public bool $onlyAlerts = false;

    public function delete(Product $product): void
    {
        $inUse = $product->invoiceItems()->exists() || $product->purchaseItems()->exists() || $product->quoteItems()->exists();

        if ($inUse) {
            $this->toastError("No se puede eliminar \"{$product->name}\" porque está referenciado en facturas, compras o presupuestos.");

            return;
        }

        $product->delete();

        $this->toastSuccess('Producto eliminado.');
    }

    public function render()
    {
        $products = Product::with('category')
            ->when($this->query !== '', function ($q) {
                $term = '%'.$this->query.'%';
                $q->where(fn ($q2) => $q2->where('name', 'like', $term)
                    ->orWhere('sku', 'like', $term)
                    ->orWhere('description', 'like', $term));
            })
            ->when($this->onlyAlerts, fn ($q) => $q->lowStock())
            ->orderBy('name')
            ->get();

        return view('livewire.products.index', [
            'products' => $products,
            'hasAnyProducts' => Product::query()->exists(),
        ]);
    }
}
