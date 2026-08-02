<?php

namespace App\Livewire\Quotes;

use App\Enums\QuoteStatus;
use App\Models\Client;
use App\Models\Product;
use App\Models\Quote;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Edit extends Component
{
    public Quote $quote;

    public string $client_id = '';

    public string $issue_date;

    public string $valid_until;

    public string $tax_rate = '0';

    public string $notes = '';

    public string $status = 'draft';

    /** @var array<int, array{product_id: ?int, description: string, quantity: string, unit_price: string}> */
    public array $items = [];

    public string $productQuery = '';

    public function mount(Quote $quote): void
    {
        $this->quote = $quote;
        $this->client_id = (string) $quote->client_id;
        $this->issue_date = $quote->issue_date->toDateString();
        $this->valid_until = $quote->valid_until->toDateString();
        $this->tax_rate = (string) $quote->tax_rate;
        $this->notes = (string) $quote->notes;
        $this->status = $quote->status->value;

        $this->items = $quote->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'description' => $item->description,
            'quantity' => (string) $item->quantity,
            'unit_price' => (string) $item->unit_price,
        ])->all();
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
            'unit_price' => (string) $product->price,
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
        ];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function subtotal(): float
    {
        return collect($this->items)->sum(fn ($item) => (float) $item['quantity'] * (float) $item['unit_price']);
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
        if ($this->quote->status === QuoteStatus::Converted) {
            return;
        }

        $this->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'issue_date' => ['required', 'date'],
            'valid_until' => ['required', 'date'],
            'tax_rate' => ['required', 'numeric', 'min:0'],
            'status' => ['required'],
            'notes' => ['nullable', 'string'],
        ]);

        $validItems = collect($this->items)->filter(fn ($item) => trim($item['description']) !== '');

        if ($validItems->isEmpty()) {
            $this->addError('items', 'Agregá al menos un ítem con descripción.');

            return;
        }

        DB::transaction(function () use ($validItems) {
            $this->quote->update([
                'client_id' => $this->client_id,
                'issue_date' => $this->issue_date,
                'valid_until' => $this->valid_until,
                'tax_rate' => $this->tax_rate,
                'notes' => $this->notes ?: null,
                'status' => $this->status,
            ]);

            $this->quote->items()->delete();
            foreach ($validItems as $item) {
                $this->quote->items()->create([
                    'product_id' => $item['product_id'],
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                ]);
            }
        });

        $this->redirect(route('quotes.show', $this->quote), navigate: true);
    }

    public function render()
    {
        return view('livewire.quotes.edit', [
            'clients' => Client::orderBy('name')->get(),
            'statuses' => QuoteStatus::editable(),
        ]);
    }
}
