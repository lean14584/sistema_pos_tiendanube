<?php

namespace App\Livewire\Reports;

use App\Support\SalesReport;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    #[Url]
    public string $fromDate;

    #[Url]
    public string $toDate;

    /** Comparar contra un segundo período elegido a mano (no el "período anterior" automático de variationPct). */
    #[Url]
    public bool $compare = false;

    #[Url]
    public string $fromDateB = '';

    #[Url]
    public string $toDateB = '';

    public function mount(): void
    {
        $this->fromDate ??= now()->subDays(30)->toDateString();
        $this->toDate ??= now()->toDateString();
        $this->fromDateB = $this->fromDateB ?: now()->subYear()->subDays(30)->toDateString();
        $this->toDateB = $this->toDateB ?: now()->subYear()->toDateString();
    }

    public function render()
    {
        $data = SalesReport::build($this->fromDate, $this->toDate);

        if ($this->compare) {
            $data['comparisonB'] = SalesReport::build($this->fromDateB, $this->toDateB);
        }

        return view('livewire.reports.index', $data);
    }
}
