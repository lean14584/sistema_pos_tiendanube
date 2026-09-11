<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Auth;

trait ScopedToSucursal
{
    /**
     * Antes solo chequeaba el rol (Admin), sin importar si el cliente tiene
     * el feature multisucursal apagado — un admin de una instalación de una
     * sola sucursal veía igual el selector "Todas las sucursales" y los
     * desgloses por sucursal.
     */
    public function puedeVerTodasLasSucursales(): bool
    {
        return config('features.multisucursal') && (bool) Auth::user()?->esAdminGlobal();
    }
}
