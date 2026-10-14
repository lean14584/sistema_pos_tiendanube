<?php

namespace App\Livewire\Providers;

use App\Livewire\Concerns\ShowsToasts;
use App\Models\Provider;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use ShowsToasts, WithPagination;

    #[Url]
    public string $query = '';

    public function updating(string $name): void
    {
        if ($name === 'query') {
            $this->resetPage();
        }
    }

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

        // Mismo chequeo que Clients\Index::delete(): provider_payments.provider_id
        // es cascadeOnDelete y puede haber pagos de cuenta corriente sin ninguna
        // compra asociada (Providers\Account::addPayment()). Sin este chequeo, el
        // cascade borra el pago por debajo de CashLinker::unlinkProviderPayment(),
        // dejando un cash_movement huérfano que ensucia el arqueo de caja.
        if ($provider->payments()->exists()) {
            $this->toastError("No se puede eliminar \"{$provider->name}\" porque tiene pagos registrados.");

            return;
        }

        $provider->delete();

        $this->toastSuccess('Proveedor eliminado.');
    }

    public function render()
    {
        $providers = Provider::query()
            ->when($this->query !== '', function ($q) {
                $term = '%'.$this->query.'%';
                $q->where(fn ($q2) => $q2->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('tax_id', 'like', $term));
            })
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.providers.index', [
            'providers' => $providers,
            'hasAnyProviders' => Provider::query()->exists(),
        ]);
    }
}
