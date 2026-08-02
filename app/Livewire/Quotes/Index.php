<?php

namespace App\Livewire\Quotes;

use App\Enums\QuoteStatus;
use App\Models\Quote;
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
        $quotes = Quote::with('client', 'items')
            ->when($this->filter !== 'all', fn ($q) => $q->where('status', $this->filter))
            ->when($this->query !== '', function ($q) {
                $term = '%'.$this->query.'%';
                $q->where(fn ($q2) => $q2->where('number', 'like', $term)
                    ->orWhereHas('client', fn ($q3) => $q3->where('name', 'like', $term)));
            })
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('livewire.quotes.index', [
            'quotes' => $quotes,
            'statuses' => QuoteStatus::cases(),
        ]);
    }
}
