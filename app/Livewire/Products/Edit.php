<?php

namespace App\Livewire\Products;

use App\Enums\AlicuotaIva;
use App\Models\Category;
use App\Models\Product;
use App\Support\CurrentSucursal;
use App\Support\StockAdjuster;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Edit extends Component
{
    use WithFileUploads;

    public Product $product;

    public string $name = '';

    public string $sku = '';

    public bool $sold_by_weight = false;

    public string $price = '';

    public string $iva_rate = '21';

    public string $cost_price = '';

    public string $stock = '0';

    public string $min_stock = '';

    public string $description = '';

    public string $category_id = '';

    /** Foto recién seleccionada, pendiente de guardar (null = no tocar la actual). */
    public $image = null;

    public function mount(Product $product): void
    {
        $this->product = $product;
        $this->name = $product->name;
        $this->sku = (string) $product->sku;
        // (bool) explícito: un Product recién creado en memoria (sin refetch)
        // puede traer null acá hasta que se lea de la base, por más que la
        // columna tenga default false.
        $this->sold_by_weight = (bool) $product->sold_by_weight;
        $this->price = (string) $product->price;
        $this->iva_rate = AlicuotaIva::normalizar($product->iva_rate);
        $this->cost_price = $product->cost_price !== null ? (string) $product->cost_price : '';
        $this->stock = (string) $product->stockEnSucursal(CurrentSucursal::id());
        $this->min_stock = $product->min_stock !== null ? (string) $product->min_stock : '';
        $this->description = (string) $product->description;
        $this->category_id = $product->category_id !== null ? (string) $product->category_id : '';
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255', Rule::requiredIf($this->sold_by_weight)],
            'sold_by_weight' => ['boolean'],
            'price' => ['required', 'numeric', 'min:0'],
            'iva_rate' => ['required', Rule::in(AlicuotaIva::valores())],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'image' => ['nullable', 'image', 'max:4096'],
        ]);

        if ($this->sold_by_weight && $data['sku'] !== null && ! ctype_digit($data['sku'])) {
            $this->addError('sku', 'El código de un producto por peso tiene que ser numérico (es el PLU que reconoce la balanza).');

            return;
        }

        $data['cost_price'] = $data['cost_price'] !== '' ? $data['cost_price'] : null;
        $data['min_stock'] = $data['min_stock'] !== '' ? $data['min_stock'] : null;
        $data['category_id'] = $data['category_id'] !== '' ? $data['category_id'] : null;
        $data['sku'] = $data['sku'] !== '' ? $data['sku'] : null;
        $data['description'] = $data['description'] !== '' ? $data['description'] : null;

        if ($this->image) {
            if ($this->product->image_path) {
                Storage::disk('public')->delete($this->product->image_path);
            }
            $data['image_path'] = $this->image->store('products', 'public');
        }
        unset($data['image']);

        // El campo "stock" del form es el de la sucursal activa: se guarda
        // como delta vía StockAdjuster (mantiene product_stocks y el
        // agregado de products.stock en sync, con su registro auditado). Los
        // productos por peso no llevan control de stock, así que se ignora.
        $sucursalId = CurrentSucursal::id();
        $delta = $data['sold_by_weight'] ? 0 : (int) $data['stock'] - $this->product->stockEnSucursal($sucursalId);
        unset($data['stock']);

        $this->product->update($data);

        if ($delta !== 0 && $sucursalId !== null) {
            StockAdjuster::applyManualDelta($this->product->id, $delta, $sucursalId);
        }

        session()->flash('status', 'Producto actualizado.');
        $this->redirect(route('products.index'), navigate: true);
    }

    /** Quita la foto actual del producto. */
    public function removeImage(): void
    {
        if ($this->product->image_path) {
            Storage::disk('public')->delete($this->product->image_path);
            $this->product->update(['image_path' => null]);
        }

        $this->image = null;
    }

    public function render()
    {
        return view('livewire.products.edit', [
            'categories' => Category::orderBy('name')->get(),
            'sucursalActiva' => CurrentSucursal::get(),
        ]);
    }
}
