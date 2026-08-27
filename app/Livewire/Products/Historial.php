<?php

namespace App\Livewire\Products;

use App\Models\AuditLog;
use App\Models\Product;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Historial extends Component
{
    public Product $product;

    public function mount(Product $product): void
    {
        $this->product = $product;
    }

    public function render()
    {
        $ajustes = $this->product->stockAdjustments()->with('user')->get()
            ->map(fn ($a) => [
                'fecha' => $a->created_at,
                'tipo' => 'Ajuste de stock',
                'detalle' => "{$a->previous_stock} → {$a->new_stock} · {$a->reason->label()}".($a->notes ? " — {$a->notes}" : ''),
                'usuario' => $a->user->name ?? 'Sistema',
            ]);

        $cambios = AuditLog::where('auditable_type', Product::class)
            ->where('auditable_id', $this->product->id)
            ->with('user')
            ->get()
            ->map(fn ($log) => [
                'fecha' => $log->created_at,
                'tipo' => match ($log->event) {
                    'created' => 'Alta',
                    'deleted' => 'Baja',
                    default => 'Modificación',
                },
                'detalle' => $this->describirCambio($log),
                'usuario' => $log->user->name ?? 'Sistema',
            ]);

        $eventos = $ajustes->concat($cambios)->sortByDesc('fecha')->values();

        return view('livewire.products.historial', ['eventos' => $eventos]);
    }

    private function describirCambio(AuditLog $log): string
    {
        return match ($log->event) {
            'created' => 'Producto creado',
            'deleted' => 'Producto eliminado',
            default => collect($log->changes)
                ->map(fn ($valores, $campo) => "{$campo}: ".($valores['old'] ?? '—').' → '.($valores['new'] ?? '—'))
                ->implode(', '),
        };
    }
}
