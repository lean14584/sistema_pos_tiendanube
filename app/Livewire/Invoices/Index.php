<?php

namespace App\Livewire\Invoices;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Sucursal;
use App\Support\CurrentSucursal;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
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

    public function puedeVerTodasLasSucursales(): bool
    {
        return (bool) Auth::user()?->esAdminGlobal();
    }

    public function render()
    {
        // Un cajero/vendedor/encargado solo ve las facturas de su propia
        // sucursal — antes se veían las de toda la empresa sin importar el rol.
        $sucursalId = $this->puedeVerTodasLasSucursales()
            ? ($this->sucursal_id !== '' ? (int) $this->sucursal_id : null)
            : CurrentSucursal::id();

        $invoices = Invoice::with('client', 'items')
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
            'sucursales' => $this->puedeVerTodasLasSucursales() ? Sucursal::orderBy('name')->get() : null,
        ]);
    }
}
