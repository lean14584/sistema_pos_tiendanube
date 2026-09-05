<?php

namespace App\Livewire\StockTransfers;

use App\Enums\StockTransferStatus;
use App\Models\StockTransfer;
use App\Support\CurrentSucursal;
use App\Support\StockAdjuster;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public StockTransfer $transfer;

    /** @var array<int, string> índice de línea => cantidad recibida (como texto, editable) */
    public array $received = [];

    public function mount(StockTransfer $transfer): void
    {
        $this->transfer = $transfer->load(['items.product', 'fromSucursal', 'toSucursal', 'user', 'receivedBy']);

        // Solo puede VER este envío quien esté parado en el origen o el
        // destino (o un admin, que ve todo). No es un dato sensible por sí
        // solo, pero tampoco hace falta exponerlo a sucursales ajenas.
        $puedeVer = auth()->user()?->esAdminGlobal()
            || CurrentSucursal::id() === $this->transfer->from_sucursal_id
            || CurrentSucursal::id() === $this->transfer->to_sucursal_id;

        abort_unless($puedeVer, 403);

        foreach ($this->transfer->items as $index => $item) {
            $this->received[$index] = (string) $item->quantity;
        }
    }

    /** Solo puede confirmar la recepción quien está parado EN EL DESTINO (o un admin). */
    public function puedeConfirmar(): bool
    {
        return $this->transfer->status === StockTransferStatus::Pendiente
            && (auth()->user()?->esAdminGlobal() || CurrentSucursal::id() === $this->transfer->to_sucursal_id);
    }

    public function confirmarRecepcion(): void
    {
        abort_unless($this->puedeConfirmar(), 403);

        $rules = [];
        foreach ($this->transfer->items as $index => $item) {
            $rules["received.{$index}"] = ['required', 'integer', 'min:0', 'max:'.$item->quantity];
        }

        $this->validate($rules, [], collect($rules)->mapWithKeys(fn ($r, $key) => [$key => 'cantidad recibida'])->all());

        DB::transaction(function () {
            foreach ($this->transfer->items as $index => $item) {
                $recibido = (int) $this->received[$index];

                $item->update(['quantity_received' => $recibido]);

                if ($recibido > 0) {
                    StockAdjuster::applyManualDelta($item->product_id, $recibido, $this->transfer->to_sucursal_id);
                }
            }

            $this->transfer->update([
                'status' => StockTransferStatus::Recibido,
                'received_at' => now(),
                'received_by_user_id' => auth()->id(),
            ]);
        });

        $this->transfer->refresh()->load('items', 'receivedBy');
        session()->flash('status', 'Recepción confirmada.');
    }

    public function render()
    {
        return view('livewire.stock-transfers.show', [
            'puedeConfirmar' => $this->puedeConfirmar(),
        ]);
    }
}
