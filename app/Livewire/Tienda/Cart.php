<?php

namespace App\Livewire\Tienda;

use App\Models\CompanySettings;
use App\Models\Product;
use App\Support\TiendaCart;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.tienda')]
class Cart extends Component
{
    public function updateQuantity(int $productId, int $quantity): void
    {
        if ($quantity > 0) {
            $stock = Product::find($productId)?->stockEnSucursal(CompanySettings::current()->tiendaOnlineSucursalId()) ?? 0;
            $quantity = min($quantity, max($stock, 1));
        }

        TiendaCart::setQuantity($productId, $quantity);
    }

    public function remove(int $productId): void
    {
        TiendaCart::remove($productId);
    }

    public function render()
    {
        $sucursalId = CompanySettings::current()->tiendaOnlineSucursalId();

        $lines = TiendaCart::lines()->map(function (array $line) use ($sucursalId) {
            $line['stock'] = $line['product']->stockEnSucursal($sucursalId);

            return $line;
        });

        return view('livewire.tienda.cart', [
            'lines' => $lines,
            'total' => (float) $lines->sum('subtotal'),
        ]);
    }
}
