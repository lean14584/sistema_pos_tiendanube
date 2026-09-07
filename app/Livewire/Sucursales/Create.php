<?php

namespace App\Livewire\Sucursales;

use App\Models\PuntoVenta;
use App\Models\Sucursal;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Create extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $razon_social = '';

    public string $punto_venta = '';

    public bool $active = true;

    public $logo = null;

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'razon_social' => ['required', 'string', 'max:255'],
            // Único en toda la empresa (no solo entre sucursales): un mismo
            // CUIT no puede repetir punto de venta, y ahora una sucursal
            // puede tener varios.
            'punto_venta' => ['required', 'integer', 'min:1', 'max:9999', 'unique:puntos_venta,numero'],
            'active' => ['boolean'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        $puntoVentaNumero = (int) $data['punto_venta'];
        unset($data['punto_venta']);

        if ($this->logo) {
            $data['logo_path'] = $this->logo->store('sucursal-logos', 'public');
        }
        unset($data['logo']);

        $sucursal = Sucursal::create($data);

        PuntoVenta::create([
            'sucursal_id' => $sucursal->id,
            'numero' => $puntoVentaNumero,
            'active' => true,
        ]);

        session()->flash('status', 'Sucursal creada.');
        $this->redirect(route('sucursales.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.sucursales.create');
    }
}
