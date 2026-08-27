<?php

namespace App\Livewire\CashRegister;

use App\Enums\CashMovementType;
use App\Enums\CashSessionStatus;
use App\Models\CashMovement;
use App\Models\CashSession;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public string $openingAmount = '';

    public string $openingNotes = '';

    public string $movType = 'ingreso';

    public string $movConcept = '';

    public string $movAmount = '';

    public string $movDate;

    public string $closingAmount = '';

    public string $closingNotes = '';

    public function mount(): void
    {
        $this->movDate = now()->toDateString();
    }

    public function openSession(): void
    {
        $this->validate([
            'openingAmount' => ['required', 'numeric', 'min:0'],
        ]);

        CashSession::create([
            'user_id' => Auth::id(),
            'status' => CashSessionStatus::Open,
            'opened_at' => now(),
            'opening_amount' => $this->openingAmount,
            'notes' => $this->openingNotes ?: null,
        ]);

        $this->openingAmount = '';
        $this->openingNotes = '';
    }

    public function addMovement(): void
    {
        $session = $this->openSessionModel();

        if (! $session) {
            return;
        }

        $this->validate([
            'movConcept' => ['required', 'string'],
            'movAmount' => ['required', 'numeric', 'min:0.01'],
            'movDate' => ['required', 'date'],
        ]);

        CashMovement::create([
            'session_id' => $session->id,
            'type' => $this->movType,
            'concept' => $this->movConcept,
            'amount' => $this->movAmount,
            'source' => 'manual',
            'date' => $this->movDate,
        ]);

        $this->movConcept = '';
        $this->movAmount = '';
    }

    /**
     * Solo se puede borrar un movimiento manual de la caja que está abierta
     * ahora mismo — nunca de una caja ya cerrada (de otro día, de otro
     * usuario). Sin este scope, cualquiera con acceso a Caja podía borrar
     * cualquier movimiento por id, tapando un faltante sin dejar rastro.
     */
    public function deleteMovement(int $movementId): void
    {
        $session = $this->openSessionModel();

        if (! $session) {
            return;
        }

        $session->movements()->whereKey($movementId)->where('source', 'manual')->first()?->delete();
    }

    public function closeSession(): void
    {
        $session = $this->openSessionModel();

        if (! $session) {
            return;
        }

        $this->validate([
            'closingAmount' => ['required', 'numeric', 'min:0'],
        ]);

        $session->update([
            'status' => CashSessionStatus::Closed,
            'closed_at' => now(),
            'closing_amount' => $this->closingAmount,
            'notes' => $this->closingNotes ?: $session->notes,
        ]);

        $this->closingAmount = '';
        $this->closingNotes = '';
    }

    private function openSessionModel(): ?CashSession
    {
        return CashSession::where('status', 'open')->first();
    }

    public function render()
    {
        $openSession = $this->openSessionModel();
        $sessionMovements = collect();
        $summary = null;

        if ($openSession) {
            $sessionMovements = $openSession->movements()->orderBy('date')->orderBy('created_at')->get();
            $ingresos = $sessionMovements->where('type', CashMovementType::Ingreso)->sum('amount');
            $egresos = $sessionMovements->where('type', CashMovementType::Egreso)->sum('amount');
            $summary = [
                'ingresos' => $ingresos,
                'egresos' => $egresos,
                'expectedClosing' => (float) $openSession->opening_amount + $ingresos - $egresos,
            ];
        }

        // Sin límite esto crecía para siempre y se recargaba entera en cada
        // acción de Caja (abrir, agregar movimiento, cerrar). Las cajas más
        // viejas se consultan en el histórico, no hace falta tenerlas acá.
        $closedSessions = CashSession::with('user', 'movements')
            ->where('status', 'closed')
            ->orderByDesc('closed_at')
            ->limit(30)
            ->get();

        return view('livewire.cash-register.index', [
            'openSession' => $openSession,
            'sessionMovements' => $sessionMovements,
            'summary' => $summary,
            'closedSessions' => $closedSessions,
        ]);
    }
}
