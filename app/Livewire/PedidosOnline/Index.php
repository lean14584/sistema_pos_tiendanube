<?php

namespace App\Livewire\PedidosOnline;

use App\Livewire\Concerns\ScopedToSucursal;
use App\Models\TiendaPedido;
use App\Support\CurrentSucursal;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listado dedicado a los pedidos de la tienda online — separado de Facturas
 * a propósito (pedido del usuario): mezclar el Remito pendiente de un
 * pedido online con las facturas reales del local confundía. Acá se
 * gestiona el ciclo completo del pedido (pago/retiro); la factura final
 * generada al entregar sigue viviendo en Facturas, como cualquier venta.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use ScopedToSucursal;
    use WithPagination;

    #[Url]
    public string $filterPago = 'all';

    #[Url]
    public string $filterRetiro = 'all';

    #[Url]
    public string $query = '';

    public function updating(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $sucursalId = $this->puedeVerTodasLasSucursales() ? null : CurrentSucursal::id();

        $pedidos = TiendaPedido::with('invoice.facturasDelRemito')
            ->when($sucursalId !== null, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->when($this->filterPago !== 'all', fn ($q) => $q->where('status', $this->filterPago))
            ->when($this->filterRetiro === 'retirado', fn ($q) => $q->whereNotNull('picked_up_at'))
            ->when($this->filterRetiro === 'pendiente', fn ($q) => $q->whereNull('picked_up_at'))
            ->when($this->query !== '', function ($q) {
                $term = '%'.$this->query.'%';
                $q->where(fn ($q2) => $q2->where('customer_name', 'like', $term)
                    ->orWhere('customer_email', 'like', $term)
                    ->orWhere('token', 'like', $term));
            })
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('livewire.pedidos-online.index', [
            'pedidos' => $pedidos,
        ]);
    }
}
