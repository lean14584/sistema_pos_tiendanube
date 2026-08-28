<?php

namespace App\Livewire\Providers;

use App\Enums\TipoDocumento;
use App\Models\Provider;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Edit extends Component
{
    public Provider $provider;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $address = '';

    public string $tax_id = '';

    public string $tipo_documento = 'cuit';

    public function mount(Provider $provider): void
    {
        $this->provider = $provider;
        $this->name = $provider->name;
        $this->email = (string) $provider->email;
        $this->phone = (string) $provider->phone;
        $this->address = (string) $provider->address;
        $this->tax_id = (string) $provider->tax_id;
        $this->tipo_documento = $provider->tipo_documento->value;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:255'],
            'tipo_documento' => ['required', Rule::enum(TipoDocumento::class)],
        ]);

        $this->provider->update($data);

        session()->flash('status', 'Proveedor actualizado.');
        $this->redirect(route('providers.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.providers.edit', [
            'tipoDocumentoOptions' => TipoDocumento::cases(),
        ]);
    }
}
