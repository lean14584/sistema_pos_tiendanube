<?php

namespace App\Livewire\Reports;

use App\Models\Sucursal;
use App\Support\CurrentSucursal;
use App\Support\SalesReport;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    #[Url]
    public string $fromDate;

    #[Url]
    public string $toDate;

    /** Comparar contra un segundo período elegido a mano (no el "período anterior" automático de variationPct). */
    #[Url]
    public bool $compare = false;

    #[Url]
    public string $fromDateB = '';

    #[Url]
    public string $toDateB = '';

    /** null = todas las sucursales consolidadas. Solo un admin global puede elegir esto. */
    #[Url]
    public string $sucursal_id = '';

    public function mount(): void
    {
        $this->fromDate ??= now()->subDays(30)->toDateString();
        $this->toDate ??= now()->toDateString();
        $this->fromDateB = $this->fromDateB ?: now()->subYear()->subDays(30)->toDateString();
        $this->toDateB = $this->toDateB ?: now()->subYear()->toDateString();
    }

    public function puedeVerTodasLasSucursales(): bool
    {
        return (bool) Auth::user()?->esAdminGlobal();
    }

    public function render()
    {
        // Un cajero/vendedor solo puede ver la suya, sin importar lo que
        // traiga la URL — el server ignora cualquier intento de forzar otra
        // sucursal desde el query string (mismo criterio que StockTransfers).
        $sucursalId = $this->puedeVerTodasLasSucursales()
            ? ($this->sucursal_id !== '' ? (int) $this->sucursal_id : null)
            : CurrentSucursal::id();

        $data = SalesReport::build($this->fromDate, $this->toDate, $sucursalId);

        if ($this->compare) {
            $data['comparisonB'] = SalesReport::build($this->fromDateB, $this->toDateB, $sucursalId);
        }

        $data['puedeVerTodasLasSucursales'] = $this->puedeVerTodasLasSucursales();
        $data['sucursales'] = $this->puedeVerTodasLasSucursales() ? Sucursal::orderBy('name')->get() : collect();

        return view('livewire.reports.index', $data);
    }
}
