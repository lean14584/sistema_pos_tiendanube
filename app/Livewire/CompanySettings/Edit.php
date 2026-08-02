<?php

namespace App\Livewire\CompanySettings;

use App\Enums\CondicionIva;
use App\Models\CompanySettings;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Edit extends Component
{
    use WithFileUploads;

    public CompanySettings $company;

    public string $cuit = '';

    public string $razon_social = '';

    public string $nombre_fantasia = '';

    public string $domicilio = '';

    public string $punto_venta = '1';

    public string $condicion_iva = 'responsable_inscripto';

    /** Archivo recién seleccionado, pendiente de guardar (null = no tocar el logo actual). */
    public $logo = null;

    public function mount(): void
    {
        $this->company = CompanySettings::current();
        $this->cuit = $this->company->cuit;
        $this->razon_social = $this->company->razon_social;
        $this->nombre_fantasia = (string) $this->company->nombre_fantasia;
        $this->domicilio = (string) $this->company->domicilio;
        $this->punto_venta = (string) $this->company->punto_venta;
        $this->condicion_iva = $this->company->condicion_iva->value;
    }

    public function save(): void
    {
        $data = $this->validate([
            'cuit' => ['required', 'digits:11'],
            'razon_social' => ['required', 'string', 'max:255'],
            'nombre_fantasia' => ['nullable', 'string', 'max:255'],
            'domicilio' => ['nullable', 'string', 'max:255'],
            'punto_venta' => ['required', 'integer', 'min:1', 'max:9999'],
            'condicion_iva' => ['required', Rule::enum(CondicionIva::class)],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($this->logo) {
            if ($this->company->logo_path) {
                Storage::disk('public')->delete($this->company->logo_path);
            }

            $data['logo_path'] = $this->logo->store('company-logos', 'public');
        }
        unset($data['logo']);

        $this->company->update($data);
        $this->logo = null;

        session()->flash('status', 'Datos de la empresa actualizados.');
    }

    public function render()
    {
        return view('livewire.company-settings.edit', [
            'condicionIvaOptions' => CondicionIva::cases(),
        ]);
    }
}
