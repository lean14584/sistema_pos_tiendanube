<?php

namespace App\Livewire\CashRegister;

use App\Enums\CashMovementType;
use App\Enums\CashSessionStatus;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Support\CurrentSucursal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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

        $sucursalId = CurrentSucursal::id();

        if ($sucursalId === null) {
            $this->addError('openingAmount', 'No hay ninguna sucursal activa para abrir caja.');

            return;
        }

        // El chequeo "¿ya hay una caja abierta EN ESTA SUCURSAL?" y la
        // creación tienen que ser atómicos: sin este lock, un doble clic en
        // "Abrir caja" crea dos sesiones open a la vez, y la segunda queda
        // huérfana (invisible para openSessionModel(), que solo trae una)
        // hasta que alguien la encuentre mezclada con el turno siguiente. El
        // lock es por sucursal para que abrir en una no bloquee a otra.
        $created = Cache::lock("caja:abrir-sesion:{$sucursalId}", 10)->block(5, function () use ($sucursalId) {
            if ($this->openSessionModel()) {
                $this->addError('openingAmount', 'Ya hay una caja abierta.');

                return false;
            }

            CashSession::create([
                'user_id' => Auth::id(),
                'sucursal_id' => $sucursalId,
                'status' => CashSessionStatus::Open,
                'opened_at' => now(),
                'opening_amount' => $this->openingAmount,
                'notes' => $this->openingNotes ?: null,
            ]);

            return true;
        });

        if ($created) {
            $this->openingAmount = '';
            $this->openingNotes = '';
        }
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
        return CashSession::where('status', 'open')->where('sucursal_id', CurrentSucursal::id())->latest('opened_at')->first();
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
        // Solo las de la sucursal activa: un admin cambia de sucursal activa
        // para ver el histórico de otro local (igual que el resto del
        // sistema desde la Fase 3).
        $closedSessions = CashSession::with('user', 'movements')
            ->where('status', 'closed')
            ->where('sucursal_id', CurrentSucursal::id())
            ->orderByDesc('closed_at')
            ->limit(30)
            ->get();

        return view('livewire.cash-register.index', [
            'openSession' => $openSession,
            'sessionMovements' => $sessionMovements,
            'summary' => $summary,
            'closedSessions' => $closedSessions,
            'sucursalActiva' => CurrentSucursal::get(),
        ]);
    }
}
