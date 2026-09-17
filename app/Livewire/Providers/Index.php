<?php

namespace App\Livewire\Providers;

use App\Livewire\Concerns\ShowsToasts;
use App\Models\Provider;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use ShowsToasts, WithPagination;

    public function delete(Provider $provider): void
    {
        // Mismo chequeo que Clients\Index::delete(): hoy solo Admin/Encargado
        // llegan a este módulo y ambos pueden eliminar, pero si el rol con
        // acceso a 'providers' se amplía en el futuro (como ya pasó con
        // 'clients'), esto evita que quede un borrado sin permiso.
        abort_unless(Auth::user()->puedeEliminar(), 403, 'Tu rol no tiene permiso para eliminar proveedores.');

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
