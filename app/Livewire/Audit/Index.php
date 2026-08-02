<?php

namespace App\Livewire\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $modelo = '';

    #[Url]
    public string $userId = '';

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
        $logs = AuditLog::query()
            ->with(['user', 'auditable'])
            ->when($this->modelo !== '', fn ($q) => $q->where('auditable_type', $this->modelo))
            ->when($this->userId !== '', fn ($q) => $q->where('user_id', $this->userId))
            ->when($this->desde !== '', fn ($q) => $q->whereDate('created_at', '>=', $this->desde))
            ->when($this->hasta !== '', fn ($q) => $q->whereDate('created_at', '<=', $this->hasta))
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('livewire.audit.index', [
            'logs' => $logs,
            'tiposAuditados' => AuditLog::tiposAuditados(),
            'usuarios' => User::orderBy('name')->get(),
        ]);
    }
}
