<?php

namespace App\Livewire\Clients;

use App\Livewire\Concerns\ShowsToasts;
use App\Models\Client;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use ShowsToasts, WithPagination;

    public function delete(Client $client): void
    {
        // Cajero tiene acceso al módulo 'clients' (para cobrar/consultar
        // cuenta corriente) pero no debería poder borrar un cliente.
        abort_unless(Auth::user()->puedeEliminar(), 403, 'Tu rol no tiene permiso para eliminar clientes.');

        if ($client->invoices()->exists()) {
            $this->toastError("No se puede eliminar al cliente \"{$client->name}\" porque tiene facturas asociadas.");

            return;
        }

        // MEJORA: quotes.client_id también tiene restrictOnDelete() (igual
        // que invoices) - sin este chequeo, un cliente con presupuestos
        // (pero sin facturas) tiraba una violación de FK sin capturar (500)
        // en vez de este mismo toast.
        if ($client->quotes()->exists()) {
            $this->toastError("No se puede eliminar al cliente \"{$client->name}\" porque tiene presupuestos asociados.");

            return;
        }

        // MEJORA: client_payments.client_id es cascadeOnDelete - borrar el
        // cliente directamente borraba sus ClientPayment a nivel de base
        // sin pasar por CashLinker::unlinkClientPayment() (la única forma
        // correcta de sacar un cobro del arqueo, ver Account::deletePayment()),
        // dejando cash_movements huérfanos apuntando a un pago que ya no
        // existe. Se bloquea en vez de intentar des-vincular en cascada acá.
        if ($client->payments()->exists()) {
            $this->toastError("No se puede eliminar al cliente \"{$client->name}\" porque tiene cobros registrados.");

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
