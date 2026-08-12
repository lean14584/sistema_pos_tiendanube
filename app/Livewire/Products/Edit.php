<?php

namespace App\Livewire\Products;

use App\Enums\AlicuotaIva;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Edit extends Component
{
    public Product $product;

    public string $name = '';

    public string $sku = '';

    public string $price = '';

    public string $iva_rate = '21';

    public string $cost_price = '';

    public string $stock = '0';

    public string $min_stock = '';

    public string $description = '';

    public string $category_id = '';

    public function mount(Product $product): void
    {
        $this->product = $product;
        $this->name = $product->name;
        $this->sku = (string) $product->sku;
        $this->price = (string) $product->price;
        $this->iva_rate = AlicuotaIva::normalizar($product->iva_rate);
        $this->cost_price = $product->cost_price !== null ? (string) $product->cost_price : '';
        $this->stock = (string) $product->stock;
        $this->min_stock = $product->min_stock !== null ? (string) $product->min_stock : '';
        $this->description = (string) $product->description;
        $this->category_id = $product->category_id !== null ? (string) $product->category_id : '';
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'iva_rate' => ['required', Rule::in(AlicuotaIva::valores())],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'exists:categories,id'],
        ]);

        $data['cost_price'] = $data['cost_price'] !== '' ? $data['cost_price'] : null;
        $data['min_stock'] = $data['min_stock'] !== '' ? $data['min_stock'] : null;
        $data['category_id'] = $data['category_id'] !== '' ? $data['category_id'] : null;

        $this->product->update($data);

        $this->redirect(route('products.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.products.edit', [
            'categories' => Category::orderBy('name')->get(),
        ]);
    }
}
