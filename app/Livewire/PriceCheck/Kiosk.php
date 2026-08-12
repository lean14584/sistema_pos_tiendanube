<?php

namespace App\Livewire\PriceCheck;

use App\Models\CompanySettings;
use App\Models\Product;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Kiosco de consulta de precios para el salón. Página pública (sin login):
 * se deja fija en una pantalla, el cliente/empleado escanea el código de
 * barras (el lector "tipea" el SKU y manda Enter) y muestra nombre + precio.
 */
#[Layout('layouts.kiosk')]
class Kiosk extends Component
{
    public string $code = '';

    /** Último producto encontrado, o null. */
    public ?array $product = null;

    /** true cuando se buscó y no se encontró nada. */
    public bool $notFound = false;

    public function search(): void
    {
        $code = trim($this->code);
        $this->code = '';
        $this->product = null;
        $this->notFound = false;

        if ($code === '') {
            return;
        }

        $producto = Product::where('sku', $code)
            ->orWhere('name', 'like', "%{$code}%")
            ->when(ctype_digit($code), fn ($q) => $q->orWhere('id', (int) $code))
            ->first();

        if ($producto) {
            $this->product = [
                'name' => $producto->name,
                'price' => (float) $producto->price,
                'sku' => $producto->sku,
                'stock' => (int) $producto->stock,
                'image' => $producto->imageUrl(),
            ];
        } else {
            $this->notFound = true;
        }

        // Que la pantalla vuelva a enfocar el input para el próximo escaneo.
        $this->dispatch('scanned');

        // Si hay algo para mostrar, arranca el temporizador de auto-reset.
        if ($this->product || $this->notFound) {
            $this->dispatch('result-shown');
        }
    }

    /**
     * Vuelve al estado inicial (lo llama el temporizador a los 10 segundos).
     */
    public function resetView(): void
    {
        $this->product = null;
        $this->notFound = false;
        $this->code = '';
        $this->dispatch('scanned');
    }

    public function render()
    {
        $company = CompanySettings::current();

        return view('livewire.price-check.kiosk', [
            'logoUrl' => $company->logo_path ? asset('storage/'.$company->logo_path) : null,
            'companyName' => $company->display_name,
        ]);
    }
}
