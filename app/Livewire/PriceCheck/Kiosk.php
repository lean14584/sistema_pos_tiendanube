<?php

namespace App\Livewire\PriceCheck;

use App\Models\CompanySettings;
use App\Models\Product;
use App\Models\Sucursal;
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

    /**
     * Sucursal a la que está pinneada ESTA pantalla física (route param
     * opcional /precios/{sucursal}). null = sin pinnear: cae al mismo
     * fallback de CurrentSucursal::id() (la primera sucursal), que en una
     * instalación de una sola sucursal ya es la respuesta correcta.
     */
    public ?Sucursal $sucursal = null;

    public function mount(?Sucursal $sucursal = null): void
    {
        $this->sucursal = $sucursal;
    }

    public function search(): void
    {
        $code = trim($this->code);
        $this->code = '';
        $this->product = null;
        $this->notFound = false;

        if ($code === '') {
            return;
        }

        // findByBarcode() cubre el sku tal cual y las dos variantes de
        // código impreso por la etiqueta (EAN13 completo y su equivalente
        // UPC-A de 12 dígitos, ver Product::findByBarcode) — antes de esto,
        // escanear la etiqueta acá no encontraba nada aunque el mismo
        // código sí anduviera en el POS.
        $producto = Product::findByBarcode($code) ?? Product::where('name', 'like', "%{$code}%")
            ->when(ctype_digit($code), fn ($q) => $q->orWhere('id', (int) $code))
            ->first();

        if ($producto) {
            $this->product = [
                'name' => $producto->name,
                'price' => (float) $producto->price,
                'sku' => $producto->sku,
                'stock' => $producto->stockEnSucursal($this->sucursal?->id),
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
