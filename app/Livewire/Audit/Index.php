<?php

namespace App\Livewire\Audit;

use App\Livewire\Concerns\ScopedToSucursal;
use App\Models\AuditLog;
use App\Models\Sucursal;
use App\Models\User;
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
    public string $modelo = '';

    #[Url]
    public string $userId = '';

    /** Solo un admin global puede elegir esto (ver render()); un encargado ve solo la suya. */
    #[Url]
    public string $sucursal_id = '';

    #[Url]
    public string $desde = '';

    #[Url]
    public string $hasta = '';

    public function updating(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        // Un encargado ve solo la auditoría de SU sucursal, sin importar lo
        // que llegue por la URL (mismo criterio que Reports\Index).
        $sucursalId = $this->puedeVerTodasLasSucursales()
            ? ($this->sucursal_id !== '' ? (int) $this->sucursal_id : null)
            : CurrentSucursal::id();

        $logs = AuditLog::query()
            ->with(['user', 'sucursal', 'auditable'])
            ->when($this->modelo !== '', fn ($q) => $q->where('auditable_type', $this->modelo))
            ->when($this->userId !== '', fn ($q) => $q->where('user_id', $this->userId))
            ->when($sucursalId !== null, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->when($this->desde !== '', fn ($q) => $q->whereDate('created_at', '>=', $this->desde))
            ->when($this->hasta !== '', fn ($q) => $q->whereDate('created_at', '<=', $this->hasta))
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('livewire.audit.index', [
            'logs' => $logs,
            'tiposAuditados' => AuditLog::tiposAuditados(),
            'usuarios' => User::orderBy('name')->get(),
            'sucursales' => $this->puedeVerTodasLasSucursales() ? Sucursal::orderBy('name')->get() : collect(),
            'puedeVerTodasLasSucursales' => $this->puedeVerTodasLasSucursales(),
        ]);
    }
}
