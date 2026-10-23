<?php

namespace App\Livewire\Quotes;

use App\Enums\QuoteStatus;
use App\Livewire\Quotes\Concerns\ManagesQuoteLines;
use App\Models\Client;
use App\Models\PriceList;
use App\Models\Quote;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Edit extends Component
{
    use ManagesQuoteLines;

    public Quote $quote;

    /**
     * Guarda contra doble-submit, mismo criterio que Invoices\Edit y
     * Purchases\Edit: sin esto, un doble-click en "Guardar" dispara dos
     * requests que pisan/duplican los items del presupuesto.
     */
    public bool $submitted = false;

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

    public function mount(Quote $quote): void
    {
        $this->quote = $quote;
        $this->client_id = (string) $quote->client_id;
        $this->price_list_id = $quote->client?->price_list_id; // null = precio base
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
            'discount' => (string) $item->discount_percent,
        ])->all();
    }

    public function save(): void
    {
        Cache::lock('quotes:edit:'.$this->quote->id, 10)->block(5, fn () => $this->saveInterno());
    }

    private function saveInterno(): void
    {
        if ($this->submitted) {
            return;
        }

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
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'between:0,100'],
        ]);

        $validItems = collect($this->items)->filter(fn ($item) => trim($item['description']) !== '');

        if ($validItems->isEmpty()) {
            $this->addError('items', 'Agregá al menos un ítem con descripción.');

            return;
        }

        // Recién acá, pasadas todas las validaciones: una falla de
        // validación legítima no debe dejar al usuario sin poder reintentar.
        $this->submitted = true;

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
                    'discount_percent' => $item['discount'] ?? 0,
                ]);
            }
        });

        session()->flash('status', 'Presupuesto actualizado.');
        $this->redirect(route('quotes.show', $this->quote), navigate: true);
    }

    public function render()
    {
        return view('livewire.quotes.edit', [
            'clients' => Client::forSelectCached(),
            'statuses' => QuoteStatus::editable(),
            'priceLists' => PriceList::activasParaSelectCached(),
        ]);
    }
}
