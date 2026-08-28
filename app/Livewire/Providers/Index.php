<?php

namespace App\Livewire\Providers;

use App\Livewire\Concerns\ShowsToasts;
use App\Models\Provider;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use ShowsToasts, WithPagination;

    public function delete(Provider $provider): void
    {
        if ($provider->purchases()->exists()) {
            $this->toastError("No se puede eliminar \"{$provider->name}\" porque tiene compras asociadas.");

            return;
        }

        $provider->delete();

        $this->toastSuccess('Proveedor eliminado.');
    }

    public function render()
    {
        return view('livewire.providers.index', [
            'providers' => Provider::orderBy('name')->paginate(20),
        ]);
    }
}
