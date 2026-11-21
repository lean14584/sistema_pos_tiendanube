<?php

namespace App\Livewire\Sucursales;

use App\Models\PuntoVenta;
use App\Models\Sucursal;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Create extends Component
{
    public string $name = '';

    public string $razon_social = '';

    public string $punto_venta = '';

    public bool $active = true;

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
        ]);

        $puntoVentaNumero = (int) $data['punto_venta'];
        unset($data['punto_venta']);

        // Dos escrituras relacionadas: una sucursal sin su punto de venta
        // (si la segunda falla) no puede facturar nada.
        $sucursal = DB::transaction(function () use ($data, $puntoVentaNumero) {
            $sucursal = Sucursal::create($data);

            PuntoVenta::create([
                'sucursal_id' => $sucursal->id,
                'numero' => $puntoVentaNumero,
                'active' => true,
            ]);

            return $sucursal;
        });

        session()->flash('status', 'Sucursal creada.');
        $this->redirect(route('sucursales.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.sucursales.create');
    }
}
