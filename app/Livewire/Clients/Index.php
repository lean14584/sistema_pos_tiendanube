<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public function delete(Client $client): void
    {
        if ($client->invoices()->exists()) {
            session()->flash('error', "No se puede eliminar al cliente \"{$client->name}\" porque tiene facturas asociadas.");

            return;
        }

        $client->delete();
    }

    public function render()
    {
        return view('livewire.clients.index', [
            'clients' => Client::orderBy('name')->paginate(20),
        ]);
    }
}
