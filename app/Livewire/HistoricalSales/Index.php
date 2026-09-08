<?php

namespace App\Livewire\HistoricalSales;

use App\Models\HistoricalSale;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $buscar = '';

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function delete(HistoricalSale $sale): void
    {
        $sale->delete();
    }

    public function render()
    {
        $ventas = HistoricalSale::query()
            ->with('client')
            ->when($this->buscar !== '', fn ($q) => $q->where('client_name_raw', 'like', '%'.$this->buscar.'%'))
            ->orderByDesc('sale_date')
            ->paginate(20);

        return view('livewire.historical-sales.index', ['ventas' => $ventas]);
    }
}
