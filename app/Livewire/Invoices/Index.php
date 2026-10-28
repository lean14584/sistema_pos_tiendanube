<?php

namespace App\Livewire\Invoices;

use App\Enums\InvoiceStatus;
use App\Enums\TipoComprobanteInterno;
use App\Livewire\Concerns\ScopedToSucursal;
use App\Models\Invoice;
use App\Models\Sucursal;
use App\Support\CurrentSucursal;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use ScopedToSucursal;
    use WithPagination;

    #[Url]
    public string $filter = 'all';

    #[Url]
    public string $query = '';

    #[Url]
    public string $sucursal_id = '';

    public function updating(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        // Un cajero/vendedor/encargado solo ve las facturas de su propia
        // sucursal — antes se veían las de toda la empresa sin importar el rol.
        $sucursalId = $this->puedeVerTodasLasSucursales()
            ? ($this->sucursal_id !== '' ? (int) $this->sucursal_id : null)
            : CurrentSucursal::id();

        $invoices = Invoice::with('client', 'items', 'tiendaPedido', 'payments')
            // El Remito de un pedido online TODAVÍA PENDIENTE vive en
            // Pedidos Online, nunca acá — mezclado con las facturas reales
            // confundía al usuario (ver feedback). Una vez entregado
            // (Invoices\Show::entregarYCobrar()) pasa a estar Pagado, y ahí
            // sí aparece — como la Factura resultante si se eligió un tipo
            // fiscal, o como el remito mismo si se eligió quedarse en
            // Remito X (no toda venta online necesita AFIP). Lo único que
            // se esconde para siempre es un remito ya superado por una
            // factura generada a partir de él.
            ->where(fn ($q) => $q->whereNull('tienda_pedido_id')
                ->orWhere('tipo_comprobante_interno', '!=', TipoComprobanteInterno::RemitoX->value)
                ->orWhere(fn ($q2) => $q2->where('status', InvoiceStatus::Paid->value)
                    ->whereDoesntHave('facturasDelRemito')))
            ->when($sucursalId !== null, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->when($this->filter !== 'all', fn ($q) => $q->withEffectiveStatus($this->filter))
            ->when($this->query !== '', function ($q) {
                $term = '%'.$this->query.'%';
                $q->where(fn ($q2) => $q2->where('number', 'like', $term)
                    ->orWhereHas('client', fn ($q3) => $q3->where('name', 'like', $term)));
            })
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('livewire.invoices.index', [
            'invoices' => $invoices,
            'statuses' => InvoiceStatus::cases(),
            'sucursales' => $this->puedeVerTodasLasSucursales() ? Sucursal::forSelectCached() : null,
        ]);
    }
}
