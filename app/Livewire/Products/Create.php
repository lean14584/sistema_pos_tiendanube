<?php

namespace App\Livewire\Products;

use App\Enums\AlicuotaIva;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductStock;
use App\Support\CurrentSucursal;
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

    public bool $sold_by_weight = false;

    public string $price = '';

    public string $iva_rate = '21';

    public string $cost_price = '';

    public string $stock = '0';

    public string $min_stock = '';

    public string $description = '';

    public string $category_id = '';

    /** Foto recién seleccionada, pendiente de guardar. */
    public $image = null;

    public function mount(): void
    {
        $this->sku = self::nextAutoSku();
    }

    /**
     * Siguiente código numérico libre, en base al mayor sku puramente
     * numérico ya cargado (ej. si el catálogo tiene "9876" y "9877", el
     * próximo es "9878"). Es solo un valor por defecto para no tener que
     * inventar un código a mano: el campo sigue editable.
     */
    public static function nextAutoSku(): string
    {
        $max = Product::query()
            ->pluck('sku')
            ->filter(fn (?string $sku) => $sku !== null && ctype_digit($sku))
            ->map(fn (string $sku) => (int) $sku)
            ->max();

        return (string) (($max ?? 0) + 1);
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

        // Los productos por peso no llevan control de stock (se reponen a
        // granel, no por unidad) — se ignora lo que se haya tipeado en Stock.
        if ($data['sold_by_weight']) {
            $data['stock'] = 0;
        }

        if ($this->image) {
            $data['image_path'] = $this->image->store('products', 'public');
        }
        unset($data['image']);

        $product = Product::create($data);

        // El stock inicial cargado acá es el de la sucursal activa: crear la
        // fila de product_stocks correspondiente (products.stock ya quedó
        // bien como agregado, por venir en $data desde la creación).
        $sucursalId = CurrentSucursal::id();
        if (! $data['sold_by_weight'] && $sucursalId !== null && (int) $data['stock'] > 0) {
            ProductStock::create(['product_id' => $product->id, 'sucursal_id' => $sucursalId, 'stock' => $data['stock']]);
        }

        session()->flash('status', 'Producto creado.');
        $this->redirect(route('products.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.products.create', [
            'categories' => Category::orderBy('name')->get(),
            'sucursalActiva' => CurrentSucursal::get(),
        ]);
    }
}
