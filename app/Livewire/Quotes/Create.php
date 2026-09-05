<?php

namespace App\Livewire\Quotes;

use App\Enums\QuoteStatus;
use App\Models\Client;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\Quote;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Create extends Component
{
    public string $client_id = '';

    public ?int $price_list_id = null;

    public string $issue_date;

    public string $valid_until;

    public string $tax_rate = '0';

    public string $notes = '';

    public string $status = 'draft';

    /** @var array<int, array{product_id: ?int, description: string, quantity: string, unit_price: string, discount: string}> */
    public array $items = [];

    public string $productQuery = '';

    public string $clientQuery = '';

    public function mount(): void
    {
        $this->issue_date = now()->toDateString();
        $this->valid_until = now()->addDays(15)->toDateString();
        $this->price_list_id = null; // precio base por defecto
    }

    public function currentPriceList(): ?PriceList
    {
        return $this->price_list_id ? PriceList::find($this->price_list_id) : null;
    }

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

    #[Computed]
    public function clientResults()
    {
        $term = trim($this->clientQuery);

        if ($term === '') {
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

    public function save(): void
    {
        $this->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'issue_date' => ['required', 'date'],
            'valid_until' => ['required', 'date'],
            'tax_rate' => ['required', 'numeric', 'min:0'],
            'status' => ['required'],
            'notes' => ['nullable', 'string'],
            'items.*.discount' => ['nullable', 'numeric', 'between:0,100'],
        ]);

        $validItems = collect($this->items)->filter(fn ($item) => trim($item['description']) !== '');

        if ($validItems->isEmpty()) {
            $this->addError('items', 'Agregá al menos un ítem con descripción.');

            return;
        }

        $quote = Cache::lock('quote-number', 10)->block(10, fn () => DB::transaction(function () use ($validItems) {
            $quote = Quote::create([
                'number' => $this->nextNumber(),
                'client_id' => $this->client_id,
                'issue_date' => $this->issue_date,
                'valid_until' => $this->valid_until,
                'tax_rate' => $this->tax_rate,
                'notes' => $this->notes ?: null,
                'status' => $this->status,
            ]);

            foreach ($validItems as $item) {
                $quote->items()->create([
                    'product_id' => $item['product_id'],
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount_percent' => $item['discount'] ?? 0,
                ]);
            }

            return $quote;
        }));

        session()->flash('status', 'Presupuesto creado.');
        $this->redirect(route('quotes.show', $quote), navigate: true);
    }

    /**
     * Antes calculaba Quote::count()+1: si se borraba un presupuesto del
     * medio, el conteo bajaba y el próximo número calculado ya existía,
     * violando el índice único de `number` y perdiendo el presupuesto al
     * guardar. Ahora sigue al último número real, como ya hace
     * InvoiceNumberGenerator para facturas/remitos.
     */
    private function nextNumber(): string
    {
        $last = Quote::where('number', 'like', 'PRE-%')->orderByDesc('id')->value('number');

        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return 'PRE-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public function render()
    {
        return view('livewire.quotes.create', [
            'clients' => Client::forSelectCached(),
            'statuses' => QuoteStatus::editable(),
            'priceLists' => PriceList::active()->orderBy('name')->get(),
        ]);
    }
}
