<?php

namespace App\Livewire\Tienda;

use App\Models\CompanySettings;
use App\Models\Product;
use App\Support\TiendaCart;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.tienda')]
class ProductDetail extends Component
{
    public Product $product;

    public int $quantity = 1;

    public bool $justAdded = false;

    public function mount(Product $product): void
    {
        $this->product = $product->load('category');
    }

    public function incrementQuantity(): void
    {
        $stock = $this->product->stockEnSucursal(CompanySettings::current()->tiendaOnlineSucursalId());

        $this->quantity = min($this->quantity + 1, max($stock, 1));
    }

    public function decrementQuantity(): void
    {
        $this->quantity = max(1, $this->quantity - 1);
    }

    public function addToCart(): void
    {
        $stock = $this->product->stockEnSucursal(CompanySettings::current()->tiendaOnlineSucursalId());

        $qty = max(1, min($this->quantity, max($stock, 1)));

        TiendaCart::add($this->product->id, $qty);

        $this->justAdded = true;
    }

    public function render()
    {
        $sucursalId = CompanySettings::current()->tiendaOnlineSucursalId();

        return view('livewire.tienda.product-detail', [
            'stock' => $this->product->stockEnSucursal($sucursalId),
        ]);
    }
}
