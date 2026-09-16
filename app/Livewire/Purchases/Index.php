<?php

namespace App\Livewire\Purchases;

use App\Enums\InvoiceStatus;
use App\Livewire\Concerns\ScopedToSucursal;
use App\Models\Purchase;
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
        // Antes se veían las compras de TODA la empresa sin importar el rol
        // — un encargado, que "manda en su sucursal", veía igual las de las
        // demás. Mismo criterio que Invoices\Index.
        $sucursalId = $this->puedeVerTodasLasSucursales()
            ? ($this->sucursal_id !== '' ? (int) $this->sucursal_id : null)
            : CurrentSucursal::id();

        $purchases = Purchase::with('provider', 'items', 'taxes')
            ->when($sucursalId !== null, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->when($this->filter !== 'all', fn ($q) => $q->withEffectiveStatus($this->filter))
            ->when($this->query !== '', function ($q) {
                $term = '%'.$this->query.'%';
                $q->where(fn ($q2) => $q2->where('number', 'like', $term)
                    ->orWhereHas('provider', fn ($q3) => $q3->where('name', 'like', $term)));
            })
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('livewire.purchases.index', [
            'purchases' => $purchases,
            'statuses' => InvoiceStatus::cases(),
            'sucursales' => $this->puedeVerTodasLasSucursales() ? Sucursal::forSelectCached() : null,
        ]);
    }
}
