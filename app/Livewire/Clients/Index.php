<?php

namespace App\Livewire\Clients;

use App\Livewire\Concerns\ShowsToasts;
use App\Models\Client;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use ShowsToasts, WithPagination;

    public function delete(Client $client): void
    {
        if ($client->invoices()->exists()) {
            $this->toastError("No se puede eliminar al cliente \"{$client->name}\" porque tiene facturas asociadas.");

            return;
        }

        $client->delete();

        $this->toastSuccess('Cliente eliminado.');
    }

    public function render()
    {
        return view('livewire.clients.index', [
            'clients' => Client::orderBy('name')->paginate(20),
        ]);
    }
}
