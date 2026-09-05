<?php

namespace App\Livewire\Sucursales;

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
            'punto_venta' => ['required', 'integer', 'min:1', 'max:9999', 'unique:sucursales,punto_venta'],
            'active' => ['boolean'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($this->logo) {
            $data['logo_path'] = $this->logo->store('sucursal-logos', 'public');
        }
        unset($data['logo']);

        Sucursal::create($data);

        session()->flash('status', 'Sucursal creada.');
        $this->redirect(route('sucursales.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.sucursales.create');
    }
}
