<?php

namespace App\Livewire\Purchases\Concerns;

use App\Models\Product;
use App\Models\Provider;
use Livewire\Attributes\Computed;

/**
 * Compartido por Purchases\Create y Purchases\Edit: manejo de los ítems,
 * impuestos/percepciones y pagos del formulario (antes de guardar) — mismo
 * criterio que ManagesInvoiceLines para facturas, ambos formularios
 * repetían este bloque casi línea por línea.
 *
 * Requiere que la clase que lo use declare `array $items`, `array $taxes`,
 * `array $payments`, `string $productQuery`, `string $providerQuery`,
 * `string $provider_id`, `string $tax_rate`, `bool $sin_detalle` y
 * `string $manual_total`.
 */
trait ManagesPurchaseLines
{
    #[Computed]
    public function productResults()
    {
        $term = trim($this->productQuery);

        if ($term === '') {
            return collect();
        }

        return Product::where('name', 'like', "%{$term}%")
            ->orWhere('sku', 'like', "%{$term}%")
            ->limit(8)
            ->get();
    }

    public function addProductItem(int $productId): void
    {
        $product = Product::findOrFail($productId);

        $this->items[] = array_merge([
            'product_id' => $product->id,
            'description' => $product->name,
            'quantity' => '1',
            'unit_price' => (string) $product->price,
        ], $this->extraItemFields());

        $this->productQuery = '';
    }

    /**
     * Campos extra que lleva cada ítem nuevo, además de los comunes de
     * arriba. Hook que Purchases\Create sobrescribe para agregar
     * batch_number/expiration_date (lote y vencimiento, ver ProductBatch) —
     * Purchases\Edit no los necesita: esa pantalla ni siquiera muestra esos
     * campos (ver el bloqueo de cantidad con lote cargado en su save()).
     *
     * @return array<string, string>
     */
    protected function extraItemFields(): array
    {
        return [];
    }

    #[Computed]
    public function providerResults()
    {
        $term = trim($this->providerQuery);

        if ($term === '') {
            return collect();
        }

        return Provider::where('name', 'like', "%{$term}%")
            ->orWhere('phone', 'like', "%{$term}%")
            ->orWhere('tax_id', 'like', "%{$term}%")
            ->limit(8)
            ->get();
    }

    public function selectProvider(int $providerId): void
    {
        $this->providerQuery = '';
        $this->provider_id = (string) $providerId;
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    /** Al activar "sin detalle" se descartan los productos ya cargados: no tendría sentido guardarlos junto a un total puesto a mano. */
    public function updatedSinDetalle(bool $value): void
    {
        if ($value) {
            $this->items = [];
        }
    }

    /**
     * Memoizadas por request (mismo criterio que
     * Pos\Index::totalSinDescuentoGlobal(): $this->items/taxes/payments no
     * cambian a mitad de un render) — remaining()/total() se llaman varias
     * veces en el mismo render de la vista (ej. en más de un @if).
     */
    public function subtotal(): float
    {
        return once(fn () => collect($this->items)->sum(fn ($item) => (float) $item['quantity'] * (float) $item['unit_price']));
    }

    public function taxAmount(): float
    {
        return $this->subtotal() * ((float) $this->tax_rate / 100);
    }

    public function percepcionesTotal(): float
    {
        return once(fn () => collect($this->taxes)->sum(fn ($t) => (float) ($t['amount'] ?? 0)));
    }

    public function addTax(): void
    {
        $this->taxes[] = ['concepto' => '', 'amount' => ''];
    }

    public function removeTax(int $index): void
    {
        unset($this->taxes[$index]);
        $this->taxes = array_values($this->taxes);
    }

    public function total(): float
    {
        if ($this->sin_detalle) {
            return (float) $this->manual_total;
        }

        return $this->subtotal() + $this->taxAmount() + $this->percepcionesTotal();
    }

    public function paidTotal(): float
    {
        return once(fn () => collect($this->payments)->sum(fn ($p) => (float) $p['amount']));
    }

    public function remaining(): float
    {
        return max(0, round($this->total() - $this->paidTotal(), 2));
    }

    public function addPayment(): void
    {
        $this->payments[] = [
            'method' => 'efectivo',
            'amount' => (string) $this->remaining(),
        ];
    }

    public function removePayment(int $index): void
    {
        unset($this->payments[$index]);
        $this->payments = array_values($this->payments);
    }

    /**
     * `wire:model.live` en el mismo renglón que el botón de "quitar" (en
     * `taxes` y `payments`) puede dejar una entrada a medio construir tras un
     * borrado + reindexado por `array_values()` — mismo mecanismo que rompió
     * `Products\Labels` en producción. Se descarta cualquier entrada
     * incompleta en vez de arriesgar una compra con una percepción o pago a
     * medio llenar.
     */
    public function updated(string $name): void
    {
        if (str_starts_with($name, 'taxes.')) {
            $this->taxes = array_values(array_filter(
                $this->taxes,
                fn (array $t) => isset($t['concepto'], $t['amount'])
            ));
        }

        if (str_starts_with($name, 'payments.')) {
            $this->payments = array_values(array_filter(
                $this->payments,
                fn (array $p) => isset($p['method'], $p['amount'])
            ));
        }
    }
}
