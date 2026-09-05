<?php

namespace App\Livewire\Invoices\Concerns;

use App\Enums\AlicuotaIva;
use App\Models\Product;

/**
 * Compartido por Invoices\Create e Invoices\Edit: manejo de los ítems y pagos
 * del formulario (antes de guardar) y el cálculo de neto/IVA/total sobre esos
 * arrays en memoria. Antes estaba copiado byte a byte en los dos componentes
 * — cualquier cambio a una regla de cálculo (redondeo, descuento, alícuotas)
 * había que acordarse de aplicarlo dos veces.
 *
 * Requiere que la clase que lo use declare `array $items` (con
 * quantity/unit_price/discount/iva_rate por ítem) y `array $payments`.
 */
trait ManagesInvoiceLines
{
    /** Neto de una línea: cantidad x precio, menos el descuento de la línea. */
    private function lineNeto(array $item): float
    {
        return (float) $item['quantity'] * (float) $item['unit_price'] * (1 - (float) ($item['discount'] ?? 0) / 100);
    }

    public function addProductItem(int $productId): void
    {
        $product = Product::findOrFail($productId);

        $this->items[] = [
            'product_id' => $product->id,
            'description' => $product->name,
            'quantity' => '1',
            'unit_price' => (string) $product->priceForList($this->currentPriceList()),
            'discount' => '0',
            'iva_rate' => AlicuotaIva::normalizar($product->iva_rate),
        ];

        $this->productQuery = '';
    }

    public function addFreeformItem(): void
    {
        $this->items[] = [
            'product_id' => null,
            'description' => '',
            'quantity' => '1',
            'unit_price' => '0',
            'discount' => '0',
            'iva_rate' => '21',
        ];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function subtotal(): float
    {
        return collect($this->items)->sum(fn ($item) => $this->lineNeto($item));
    }

    public function netoGravado(): float
    {
        return collect($this->items)
            ->filter(fn ($item) => (float) ($item['iva_rate'] ?? 0) > 0)
            ->sum(fn ($item) => $this->lineNeto($item));
    }

    public function netoExento(): float
    {
        return collect($this->items)
            ->filter(fn ($item) => (float) ($item['iva_rate'] ?? 0) <= 0)
            ->sum(fn ($item) => $this->lineNeto($item));
    }

    public function taxAmount(): float
    {
        return collect($this->items)->sum(
            fn ($item) => $this->lineNeto($item) * ((float) ($item['iva_rate'] ?? 0) / 100)
        );
    }

    public function total(): float
    {
        return $this->subtotal() + $this->taxAmount();
    }

    /**
     * Desglose del IVA por alícuota para mostrar en el formulario.
     *
     * @return array<int, array{tasa: float, iva: float}>
     */
    public function ivaBreakdown(): array
    {
        return collect($this->items)
            ->filter(fn ($item) => (float) ($item['iva_rate'] ?? 0) > 0)
            ->groupBy(fn ($item) => (string) (float) $item['iva_rate'])
            ->map(fn ($grupo, $tasa) => [
                'tasa' => (float) $tasa,
                'iva' => $grupo->sum(fn ($item) => $this->lineNeto($item) * ((float) $item['iva_rate'] / 100)),
            ])
            ->sortBy('tasa')
            ->values()
            ->all();
    }

    public function paidTotal(): float
    {
        return collect($this->payments)->sum(fn ($p) => (float) $p['amount']);
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
}
