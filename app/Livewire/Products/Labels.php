<?php

namespace App\Livewire\Products;

use App\Livewire\Concerns\ShowsToasts;
use App\Models\Category;
use App\Models\CompanySettings;
use App\Models\PriceList;
use App\Models\Product;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Labels extends Component
{
    use ShowsToasts;

    public string $productQuery = '';

    /** @var array<int, array{id:int,name:string,sku:?string,qty:int}> */
    public array $selected = [];

    public int $columns = 3;

    public bool $showSku = true;

    public bool $showName = true;

    public bool $showCompany = true;

    public ?int $price_list_id = null; // null = precio base

    public ?int $catToAdd = null;

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

    public function addProduct(int $productId): void
    {
        $product = Product::find($productId);

        if (! $product) {
            return;
        }

        if (isset($this->selected[$productId])) {
            $this->selected[$productId]['qty']++;
        } else {
            $this->selected[$productId] = [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'qty' => 1,
            ];
        }

        $this->productQuery = '';
    }

    /** Tope de productos por categoría al agregar de una: son etiquetas que
     * viajan al navegador en el estado de Livewire, no una tabla paginada. */
    private const MAX_POR_CATEGORIA = 300;

    public function addCategory(): void
    {
        if (! $this->catToAdd) {
            return;
        }

        $total = Product::where('category_id', $this->catToAdd)->count();

        Product::where('category_id', $this->catToAdd)->orderBy('name')->limit(self::MAX_POR_CATEGORIA)->get()
            ->each(fn (Product $p) => $this->addProduct($p->id));

        if ($total > self::MAX_POR_CATEGORIA) {
            $this->toastError("La categoría tiene {$total} productos: se agregaron los primeros ".self::MAX_POR_CATEGORIA.' por nombre. Agregá el resto a mano o de a partes.');
        }

        $this->catToAdd = null;
    }

    public function removeProduct(int $productId): void
    {
        unset($this->selected[$productId]);
    }

    public function clear(): void
    {
        $this->selected = [];
    }

    /**
     * Expande la selección a una etiqueta por unidad, ya con el precio según
     * la lista elegida. Es lo que se dibuja en la hoja imprimible.
     *
     * @return \Illuminate\Support\Collection<int, array{name:string,sku:?string,price:float}>
     */
    private function labels()
    {
        $list = $this->price_list_id ? PriceList::find($this->price_list_id) : null;
        $ids = array_keys($this->selected);
        $products = Product::whereIn('id', $ids)->get()->keyBy('id');

        $labels = collect();

        foreach ($this->selected as $id => $row) {
            $product = $products->get($id);

            if (! $product) {
                continue;
            }

            $price = $product->priceForList($list);

            for ($i = 0; $i < max(1, (int) $row['qty']); $i++) {
                $labels->push([
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'price' => $price,
                ]);
            }
        }

        return $labels;
    }

    public function render()
    {
        return view('livewire.products.labels', [
            'labels' => $this->labels(),
            'categories' => Category::orderBy('name')->get(),
            'priceLists' => PriceList::active()->orderBy('name')->get(),
            'companyName' => CompanySettings::current()->display_name,
        ]);
    }
}
