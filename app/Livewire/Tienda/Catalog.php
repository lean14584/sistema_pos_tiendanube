<?php

namespace App\Livewire\Tienda;

use App\Models\Category;
use App\Models\CompanySettings;
use App\Models\Product;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Catálogo público de la tienda online: lee directo de Product/Category, la
 * misma tabla que usa el POS (catálogo y stock siempre al día, sin paso de
 * sincronización — ver app/Livewire/PriceCheck/Kiosk.php para el mismo
 * patrón de página pública sin auth).
 */
#[Layout('layouts.tienda')]
class Catalog extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $query = '';

    #[Url(as: 'categoria')]
    public ?int $categoryId = null;

    #[Url(as: 'orden')]
    public string $sort = 'recientes';

    public function updating(string $name): void
    {
        if (in_array($name, ['query', 'categoryId', 'sort'], true)) {
            $this->resetPage();
        }
    }

    public function limpiarFiltros(): void
    {
        $this->reset(['query', 'categoryId', 'sort']);
        $this->resetPage();
    }

    public function render()
    {
        $sucursalId = CompanySettings::current()->tiendaOnlineSucursalId();

        $products = Product::with(['category', 'stocks' => fn ($q) => $q->where('sucursal_id', $sucursalId)])
            ->when($this->query !== '', function ($q) {
                $term = '%'.$this->query.'%';
                $q->where(fn ($q2) => $q2->where('name', 'like', $term)
                    ->orWhere('description', 'like', $term));
            })
            ->when($this->categoryId, fn ($q) => $q->where('category_id', $this->categoryId))
            ->when($this->sort === 'precio_asc', fn ($q) => $q->orderBy('price'))
            ->when($this->sort === 'precio_desc', fn ($q) => $q->orderByDesc('price'))
            ->when($this->sort === 'recientes', fn ($q) => $q->latest('id'))
            ->paginate(20);

        return view('livewire.tienda.catalog', [
            'products' => $products,
            'categories' => Category::forSelectCached(),
            'sucursalId' => $sucursalId,
        ]);
    }
}
