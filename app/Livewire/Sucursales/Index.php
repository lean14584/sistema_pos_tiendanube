<?php

namespace App\Livewire\Sucursales;

use App\Livewire\Concerns\ShowsToasts;
use App\Models\Sucursal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    use ShowsToasts;

    public function delete(Sucursal $sucursal): void
    {
        // Mismo chequeo que Clients\Index::delete(): hoy solo Admin llega a
        // este módulo y puede eliminar, pero es defensa en profundidad si el
        // acceso a 'sucursales' se amplía en el futuro.
        abort_unless(Auth::user()->puedeEliminar(), 403, 'Tu rol no tiene permiso para eliminar sucursales.');

        if (Sucursal::count() <= 1) {
            $this->toastError('No se puede eliminar la única sucursal.');

            return;
        }

        if ($sucursal->users()->exists()) {
            $this->toastError("No se puede eliminar \"{$sucursal->name}\" porque tiene usuarios asignados.");

            return;
        }

        if ($sucursal->productStocks()->where('stock', '>', 0)->exists()) {
            $this->toastError("No se puede eliminar \"{$sucursal->name}\" porque todavía tiene stock cargado. Pasalo a otra sucursal con Ajuste de Stock primero.");

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
            // MEJORA: la columna "Punto de venta" leía Sucursal::punto_venta,
            // que ya no existe desde que una sucursal puede tener varios
            // (ver migración create_puntos_venta_table / feature cajas
            // múltiples) - la tabla mostraba siempre "0000". Se trae la
            // relación real para listar los números activos.
            'sucursales' => Sucursal::with(['puntosVenta' => fn ($q) => $q->where('active', true)->orderBy('id')])
                ->orderBy('name')
                ->get(),
        ]);
    }
}
