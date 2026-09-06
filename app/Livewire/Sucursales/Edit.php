<?php

namespace App\Livewire\Sucursales;

use App\Models\Sucursal;
use App\Models\SucursalMercadoPagoConfig;
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

    // --- Mercado Pago (ver SucursalMercadoPagoConfig) ---
    public string $mp_access_token = '';

    public string $mp_store_external_id = '';

    public string $mp_pos_external_id = '';

    public string $mp_store_name = '';

    public string $mp_pos_name = '';

    public function mount(Sucursal $sucursal): void
    {
        $this->sucursal = $sucursal;
        $this->name = $sucursal->name;
        $this->razon_social = $sucursal->razon_social;
        $this->punto_venta = (string) $sucursal->punto_venta;
        $this->active = $sucursal->active;

        $mp = $sucursal->mercadoPagoConfig;
        // El access_token NO se precarga (mismo criterio que Tiendanube\Index):
        // un input type=password con el valor real lo mandaría en texto plano
        // al navegador. Se muestra solo "cargado/no cargado" (ver
        // mpTokenCargado()); vacío al guardar significa "no cambiar".
        $this->mp_store_external_id = (string) ($mp->store_external_id ?? '');
        $this->mp_pos_external_id = (string) ($mp->pos_external_id ?? '');
        $this->mp_store_name = (string) ($mp->store_name ?? '');
        $this->mp_pos_name = (string) ($mp->pos_name ?? '');
    }

    public function mpTokenCargado(): bool
    {
        return filled($this->sucursal->mercadoPagoConfig?->access_token);
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'razon_social' => ['required', 'string', 'max:255'],
            'punto_venta' => ['required', 'integer', 'min:1', 'max:9999', Rule::unique('sucursales', 'punto_venta')->ignore($this->sucursal->id)],
            'active' => ['boolean'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'mp_access_token' => ['nullable', 'string', 'max:255'],
            'mp_store_external_id' => ['nullable', 'string', 'max:100'],
            'mp_pos_external_id' => ['nullable', 'string', 'max:100'],
            'mp_store_name' => ['nullable', 'string', 'max:255'],
            'mp_pos_name' => ['nullable', 'string', 'max:255'],
        ]);

        if ($this->logo) {
            if ($this->sucursal->logo_path) {
                Storage::disk('public')->delete($this->sucursal->logo_path);
            }

            $data['logo_path'] = $this->logo->store('sucursal-logos', 'public');
        }
        unset($data['logo']);

        $hayDatosDeMp = filled($data['mp_access_token']) || filled($data['mp_store_external_id'])
            || filled($data['mp_pos_external_id']) || filled($data['mp_store_name']) || filled($data['mp_pos_name']);

        if ($hayDatosDeMp || $this->sucursal->mercadoPagoConfig) {
            $mpData = [
                'store_external_id' => $data['mp_store_external_id'] ?: null,
                'pos_external_id' => $data['mp_pos_external_id'] ?: null,
                'store_name' => $data['mp_store_name'] ?: null,
                'pos_name' => $data['mp_pos_name'] ?: null,
            ];
            // Campo vacío = "no cambiar" el token ya guardado (nunca se
            // precarga el real, así que no hay forma de distinguir "lo vacié
            // a propósito" de "no lo toqué").
            if (filled($data['mp_access_token'])) {
                $mpData['access_token'] = $data['mp_access_token'];
            }

            SucursalMercadoPagoConfig::updateOrCreate(['sucursal_id' => $this->sucursal->id], $mpData);
        }

        unset($data['mp_access_token'], $data['mp_store_external_id'], $data['mp_pos_external_id'], $data['mp_store_name'], $data['mp_pos_name']);

        $this->sucursal->update($data);

        session()->flash('status', 'Sucursal actualizada.');
        $this->redirect(route('sucursales.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.sucursales.edit');
    }
}
