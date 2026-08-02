<?php

namespace App\Livewire\Invoices;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
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

    public function updating(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $invoices = Invoice::with('client', 'items')
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
        ]);
    }
}
