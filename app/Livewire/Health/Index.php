<?php

namespace App\Livewire\Health;

use App\Support\SystemHealth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public function render()
    {
        $chequeos = app(SystemHealth::class)->chequeos();

        return view('livewire.health.index', [
            'chequeos' => $chequeos,
            'avisos' => $chequeos->whereIn('estado', ['warning', 'error'])->count(),
        ]);
    }
}
