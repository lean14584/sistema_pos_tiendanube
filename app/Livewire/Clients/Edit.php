<?php

namespace App\Livewire\Clients;

use App\Enums\CondicionIva;
use App\Enums\TipoDocumento;
use App\Models\Client;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Edit extends Component
{
    public Client $client;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $address = '';

    public string $tax_id = '';

    public string $condicion_iva = 'consumidor_final';

    public string $tipo_documento = 'sin_identificar';

    public function mount(Client $client): void
    {
        $this->client = $client;
        $this->name = $client->name;
        $this->email = $client->email;
        $this->phone = (string) $client->phone;
        $this->address = (string) $client->address;
        $this->tax_id = (string) $client->tax_id;
        $this->condicion_iva = $client->condicion_iva->value;
        $this->tipo_documento = $client->tipo_documento->value;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:255'],
            'condicion_iva' => ['required', Rule::enum(CondicionIva::class)],
            'tipo_documento' => ['required', Rule::enum(TipoDocumento::class)],
        ]);

        $this->client->update($data);

        $this->redirect(route('clients.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.clients.edit', [
            'condicionIvaOptions' => CondicionIva::cases(),
            'tipoDocumentoOptions' => TipoDocumento::cases(),
        ]);
    }
}
