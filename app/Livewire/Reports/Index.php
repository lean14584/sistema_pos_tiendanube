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

    public function mount(): void
    {
        $this->fromDate ??= now()->subDays(30)->toDateString();
        $this->toDate ??= now()->toDateString();
    }

    public function render()
    {
        return view('livewire.reports.index', SalesReport::build($this->fromDate, $this->toDate));
    }
}
