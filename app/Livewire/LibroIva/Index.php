<?php

namespace App\Livewire\LibroIva;

use App\Support\LibroIva\LibroIvaCalculator;
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

    #[Url]
    public string $tab = 'ventas';

    public function mount(): void
    {
        $this->fromDate ??= now()->startOfMonth()->toDateString();
        $this->toDate ??= now()->endOfMonth()->toDateString();
    }

    public function render()
    {
        $rows = $this->tab === 'compras'
            ? LibroIvaCalculator::compras($this->fromDate, $this->toDate)
            : LibroIvaCalculator::ventas($this->fromDate, $this->toDate);

        $resumen = LibroIvaCalculator::resumenPorAlicuota($rows);

        return view('livewire.libro-iva.index', [
            'rows' => $rows,
            'resumen' => $resumen,
            'totalNeto' => $resumen->sum('netoGravado'),
            'totalIva' => $resumen->sum('iva'),
            'totalExento' => $rows->sum('importeExento'),
            'totalGeneral' => $rows->sum('importeTotal'),
        ]);
    }
}
