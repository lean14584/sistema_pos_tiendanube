<?php

namespace App\Livewire\Providers;

use App\Enums\TipoDocumento;
use App\Models\Provider;
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

    public string $tipo_documento = 'cuit';

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

        Provider::create($data);

        $this->redirect(route('providers.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.providers.create', [
            'tipoDocumentoOptions' => TipoDocumento::cases(),
        ]);
    }
}
