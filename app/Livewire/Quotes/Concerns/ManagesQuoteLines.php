<?php

namespace App\Livewire\Quotes\Concerns;

use App\Models\Client;
use App\Models\PriceList;
use App\Models\Product;
use Livewire\Attributes\Computed;

/**
 * Compartido por Quotes\Create y Quotes\Edit: manejo de los ítems del
 * formulario (buscador de clientes/productos, repricing por lista de
 * precios, cálculo de neto/IVA/total) — antes estaba copiado byte a byte
 * entre los dos, igual que pasaba con Invoices\Create/Edit antes de
 * CalculatesInvoiceTotals/ManagesInvoiceLines. Cualquier fix (ej. el mínimo
 * de 2 caracteres para no escanear toda la tabla en cada tecla) quedaba
 * aplicado en uno solo de los dos componentes sin que nadie lo notara.
 *
 * Requiere que la clase que lo use declare `array $items` (con
 * quantity/unit_price/discount por ítem, sin iva_rate — un Presupuesto no
 * calcula IVA por línea, usa un único `tax_rate` sobre el subtotal),
 * `string $client_id`, `?int $price_list_id`, `string $tax_rate`,
 * `string $productQuery` y `string $clientQuery`.
 */
trait ManagesQuoteLines
{
    public function updatedClientId($value): void
    {
        $client = Client::find($value);
        $this->price_list_id = $client?->price_list_id;
        $this->repriceItems();
    }

    public function updatedPriceListId(): void
    {
        $this->repriceItems();
    }

    /** Lista de precios vigente. null = precio base (sin ajuste). */
    public function currentPriceList(): ?PriceList
    {
        // FEATURE_PRICE_LISTS=false apagaba solo la pantalla de
        // administración de listas (routes/web.php) — el selector de acá
        // seguía aplicando la lista del cliente/elegida igual, así que el
        // flag no desactivaba nada de lo que su nombre promete (ver el mismo
        // comentario en Invoices\Create::currentPriceList()).
        if (! config('features.price_lists')) {
            return null;
        }

        return $this->price_list_id ? PriceList::find($this->price_list_id) : null;
    }

    #[Computed]
    public function clientResults()
    {
        $term = trim($this->clientQuery);

        // Con 1 solo caracter el LIKE '%x%' escanea toda la tabla en cada
        // tecla sin acotar casi nada el resultado — se pide un mínimo.
        if (mb_strlen($term) < 2) {
            return collect();
        }

        return Client::where('name', 'like', "%{$term}%")
            ->orWhere('phone', 'like', "%{$term}%")
            ->limit(8)
            ->get();
    }

    public function selectClient(int $clientId): void
    {
        $this->clientQuery = '';
        $this->client_id = (string) $clientId;
        $this->updatedClientId($clientId);
    }

    /** Reaplica el precio de la lista a los ítems que provienen de un producto. */
    private function repriceItems(): void
    {
        $list = $this->currentPriceList();
        $productIds = collect($this->items)->pluck('product_id')->filter()->unique()->all();
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        foreach ($this->items as $i => $item) {
            if (! empty($item['product_id']) && ($product = $products->get($item['product_id']))) {
                $this->items[$i]['unit_price'] = (string) $product->priceForList($list);
            }
        }
    }

    private function lineNeto(array $item): float
    {
        return (float) $item['quantity'] * (float) $item['unit_price'] * (1 - (float) ($item['discount'] ?? 0) / 100);
    }

    #[Computed]
    public function productResults()
    {
        $term = trim($this->productQuery);

        // Con 1 solo caracter el LIKE '%x%' escanea toda la tabla en cada
        // tecla sin acotar casi nada el resultado — se pide un mínimo.
        if (mb_strlen($term) < 2) {
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

        $this->items[] = [
            'product_id' => $product->id,
            'description' => $product->name,
            'quantity' => '1',
            'unit_price' => (string) $product->priceForList($this->currentPriceList()),
            'discount' => '0',
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
        ];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    /**
     * `wire:model.live` de "discount" vive en el mismo renglón que el botón
     * de "quitar" — un update en vuelo puede dejar una entrada a medio
     * construir tras un borrado + reindexado por `array_values()`, mismo
     * mecanismo que rompió `Products\Labels` en producción. Se descarta
     * cualquier entrada incompleta en vez de arriesgar un presupuesto con un
     * ítem a medio llenar.
     */
    public function updated(string $name): void
    {
        if (str_starts_with($name, 'items.')) {
            $this->items = array_values(array_filter(
                $this->items,
                fn (array $i) => isset($i['description'], $i['quantity'], $i['unit_price'], $i['discount'])
            ));
        }
    }

    public function subtotal(): float
    {
        return collect($this->items)->sum(fn ($item) => $this->lineNeto($item));
    }

    public function taxAmount(): float
    {
        return $this->subtotal() * ((float) $this->tax_rate / 100);
    }

    public function total(): float
    {
        return $this->subtotal() + $this->taxAmount();
    }
}
