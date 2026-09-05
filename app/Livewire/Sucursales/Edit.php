<?php

namespace App\Livewire\Sucursales;

use App\Models\Sucursal;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Edit extends Component
{
    use WithFileUploads;

    public Sucursal $sucursal;

    public string $name = '';

    public string $razon_social = '';

    public string $punto_venta = '';

    public bool $active = true;

    /** Archivo recién seleccionado, pendiente de guardar (null = no tocar el logo actual). */
    public $logo = null;

    public function mount(Sucursal $sucursal): void
    {
        $this->sucursal = $sucursal;
        $this->name = $sucursal->name;
        $this->razon_social = $sucursal->razon_social;
        $this->punto_venta = (string) $sucursal->punto_venta;
        $this->active = $sucursal->active;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'razon_social' => ['required', 'string', 'max:255'],
            'punto_venta' => ['required', 'integer', 'min:1', 'max:9999', Rule::unique('sucursales', 'punto_venta')->ignore($this->sucursal->id)],
            'active' => ['boolean'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($this->logo) {
            if ($this->sucursal->logo_path) {
                Storage::disk('public')->delete($this->sucursal->logo_path);
            }

            $data['logo_path'] = $this->logo->store('sucursal-logos', 'public');
        }
        unset($data['logo']);

        $this->sucursal->update($data);

        session()->flash('status', 'Sucursal actualizada.');
        $this->redirect(route('sucursales.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.sucursales.edit');
    }
}
