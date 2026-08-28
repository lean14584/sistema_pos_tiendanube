<?php

namespace App\Livewire\Products;

use App\Enums\AlicuotaIva;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Create extends Component
{
    use WithFileUploads;
    public string $name = '';

    public string $sku = '';

    public string $price = '';

    public string $iva_rate = '21';

    public string $cost_price = '';

    public string $stock = '0';

    public string $min_stock = '';

    public string $description = '';

    public string $category_id = '';

    /** Foto recién seleccionada, pendiente de guardar. */
    public $image = null;

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
            'image' => ['nullable', 'image', 'max:4096'],
        ]);

        $data['cost_price'] = $data['cost_price'] !== '' ? $data['cost_price'] : null;
        $data['min_stock'] = $data['min_stock'] !== '' ? $data['min_stock'] : null;
        $data['category_id'] = $data['category_id'] !== '' ? $data['category_id'] : null;
        $data['sku'] = $data['sku'] !== '' ? $data['sku'] : null;
        $data['description'] = $data['description'] !== '' ? $data['description'] : null;

        if ($this->image) {
            $data['image_path'] = $this->image->store('products', 'public');
        }
        unset($data['image']);

        Product::create($data);

        session()->flash('status', 'Producto creado.');
        $this->redirect(route('products.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.products.create', [
            'categories' => Category::orderBy('name')->get(),
        ]);
    }
}
