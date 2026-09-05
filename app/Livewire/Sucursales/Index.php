<?php

namespace App\Livewire\Sucursales;

use App\Livewire\Concerns\ShowsToasts;
use App\Models\Sucursal;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    use ShowsToasts;

    public function delete(Sucursal $sucursal): void
    {
        if (Sucursal::count() <= 1) {
            $this->toastError('No se puede eliminar la única sucursal.');

            return;
        }

        if ($sucursal->users()->exists()) {
            $this->toastError("No se puede eliminar \"{$sucursal->name}\" porque tiene usuarios asignados.");

            return;
        }

        if ($sucursal->logo_path) {
            Storage::disk('public')->delete($sucursal->logo_path);
        }

        $sucursal->delete();

        $this->toastSuccess('Sucursal eliminada.');
    }

    public function render()
    {
        return view('livewire.sucursales.index', [
            'sucursales' => Sucursal::orderBy('name')->get(),
        ]);
    }
}
