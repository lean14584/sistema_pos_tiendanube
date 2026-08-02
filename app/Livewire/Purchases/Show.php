<?php

namespace App\Livewire\Purchases;

use App\Enums\InvoiceStatus;
use App\Models\Purchase;
use App\Support\StockAdjuster;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Purchase $purchase;

    public function mount(Purchase $purchase): void
    {
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

            StockAdjuster::apply($items, -1);
            $this->purchase->delete();
        });

        $this->redirect(route('purchases.index'), navigate: true);
    }

    public function render()
    {
        $this->purchase->load('provider', 'items');

        return view('livewire.purchases.show', [
            'statuses' => InvoiceStatus::cases(),
        ]);
    }
}
