<?php

namespace App\Livewire\Clients;

use App\Enums\CondicionIva;
use App\Enums\TipoDocumento;
use App\Models\Client;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Create extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $address = '';

    public string $tax_id = '';

    public string $condicion_iva = 'consumidor_final';

    public string $tipo_documento = 'sin_identificar';

    public ?int $price_list_id = null;

    public string $credit_limit = '';

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
            'price_list_id' => ['nullable', 'exists:price_lists,id'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
        ]);

        $data['credit_limit'] = $this->credit_limit === '' ? null : $this->credit_limit;

        Client::create($data);

        $this->redirect(route('clients.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.clients.create', [
            'condicionIvaOptions' => CondicionIva::cases(),
            'tipoDocumentoOptions' => TipoDocumento::cases(),
            'priceLists' => \App\Models\PriceList::active()->orderBy('name')->get(),
        ]);
    }
}
