<?php

namespace App\Livewire\Tienda;

use App\Models\TiendaPedido;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.tienda')]
class OrderStatus extends Component
{
    public TiendaPedido $pedido;

    public function mount(string $token): void
    {
        $this->pedido = TiendaPedido::where('token', $token)
            ->with(['invoice.items.product'])
            ->firstOrFail();
    }

    public function render()
    {
        return view('livewire.tienda.order-status');
    }
}
