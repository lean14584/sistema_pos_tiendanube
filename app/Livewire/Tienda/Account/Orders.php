<?php

namespace App\Livewire\Tienda\Account;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.tienda')]
class Orders extends Component
{
    public function render()
    {
        $pedidos = Auth::guard('customer')->user()
            ->pedidos()
            ->with('invoice')
            ->latest()
            ->get();

        return view('livewire.tienda.account.orders', ['pedidos' => $pedidos]);
    }
}
