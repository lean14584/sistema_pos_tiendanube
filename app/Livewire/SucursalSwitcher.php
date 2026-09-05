<?php

namespace App\Livewire;

use App\Models\Sucursal;
use App\Support\CurrentSucursal;
use Livewire\Component;

/**
 * Selector de "sucursal activa" para un admin (global: puede pararse en
 * cualquiera). Cajero/vendedor no lo ven — la suya es fija (ver sidebar).
 */
class SucursalSwitcher extends Component
{
    public string $sucursalId = '';

    public function mount(): void
    {
        $this->sucursalId = (string) CurrentSucursal::id();
    }

    public function updatedSucursalId(): void
    {
        if ($this->sucursalId === '') {
            return;
        }

        CurrentSucursal::set((int) $this->sucursalId);

        // Un cambio de sucursal activa afecta a componentes que ya
        // renderizaron con la anterior (stock, caja, etc.): la forma
        // simple y segura de que todos queden consistentes es recargar.
        $this->js('window.location.reload()');
    }

    public function render()
    {
        return view('livewire.sucursal-switcher', [
            'sucursales' => Sucursal::where('active', true)->orderBy('name')->get(),
        ]);
    }
}
