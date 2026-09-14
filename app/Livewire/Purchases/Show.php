<?php

namespace App\Livewire\Purchases;

use App\Enums\InvoiceStatus;
use App\Models\Purchase;
use App\Support\CashLinker;
use App\Support\CurrentSucursal;
use App\Support\StockAdjuster;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Purchase $purchase;

    public function mount(Purchase $purchase): void
    {
        // El listado ya filtra por sucursal, pero antes de esto el acceso
        // directo a una compra puntual (cambiando el id en la URL) no
        // chequeaba nada — mismo hueco que tenía Invoices\Show.
        abort_unless(
            Auth::user()?->esAdminGlobal() || $purchase->sucursal_id === CurrentSucursal::id(),
            403,
            'No tenés acceso a esta compra.'
        );

        $this->purchase = $purchase;
    }

    public function setStatus(string $status): void
    {
        $this->purchase->update(['status' => $status]);
    }

    public function delete(): void
    {
        DB::transaction(function () {
            $items = $this->purchase->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'quantity' => (float) $item->quantity,
            ])->all();

            // La sucursal de la compra, no la activa de quien la borra
            // ahora (ver migración add_sucursal_id_to_purchases_table).
            StockAdjuster::apply($items, -1, $this->purchase->sucursal_id);

            // MEJORA: los lotes de esta compra sobrevivían con purchase_id
            // en null (nullOnDelete) y su quantity_remaining intacta, pese a
            // que el stock que representaban ya se revirtió arriba — quedaban
            // como lotes "fantasma" en Lotes y Vencimientos, y darlos de baja
            // después descontaba stock que ya no existía.
            $this->purchase->batches()->delete();

            $this->purchase->payments->each(fn ($payment) => CashLinker::unlinkPurchasePayment($payment));

            $this->purchase->delete();
        });

        session()->flash('status', 'Compra eliminada.');
        $this->redirect(route('purchases.index'), navigate: true);
    }

    public function render()
    {
        $this->purchase->load('provider', 'items', 'payments');

        return view('livewire.purchases.show', [
            'statuses' => InvoiceStatus::cases(),
        ]);
    }
}
