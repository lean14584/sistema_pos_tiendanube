<?php

namespace App\Livewire\Purchases;

use App\Enums\InvoiceStatus;
use App\Models\Purchase;
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
        $purchases = Purchase::with('provider', 'items', 'taxes')
            ->when($this->filter !== 'all', fn ($q) => $q->withEffectiveStatus($this->filter))
            ->when($this->query !== '', function ($q) {
                $term = '%'.$this->query.'%';
                $q->where(fn ($q2) => $q2->where('number', 'like', $term)
                    ->orWhereHas('provider', fn ($q3) => $q3->where('name', 'like', $term)));
            })
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('livewire.purchases.index', [
            'purchases' => $purchases,
            'statuses' => InvoiceStatus::cases(),
        ]);
    }
}
