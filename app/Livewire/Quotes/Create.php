<?php

namespace App\Livewire\Quotes;

use App\Enums\QuoteStatus;
use App\Livewire\Quotes\Concerns\ManagesQuoteLines;
use App\Models\Client;
use App\Models\PriceList;
use App\Models\Quote;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Create extends Component
{
    use ManagesQuoteLines;

    public string $client_id = '';

    public ?int $price_list_id = null;

    public string $issue_date;

    public string $valid_until;

    public string $tax_rate = '0';

    public string $notes = '';

    public string $status = 'draft';

    /**
     * Guarda contra doble-submit: mismo criterio que Invoices\Create y
     * Purchases\Create — este form no tiene ningún estado persistente
     * natural para detectar "esto ya se guardó". El lock de 'quote-number'
     * de más abajo solo serializa la NUMERACIÓN, no evita que dos submits
     * del mismo click doble generen dos presupuestos completos.
     */
    public bool $submitted = false;

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

    public function save(): void
    {
        Cache::lock('quotes:create:'.Auth::id(), 10)->block(5, fn () => $this->saveInterno());
    }

    private function saveInterno(): void
    {
        if ($this->submitted) {
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
            'priceLists' => config('features.price_lists') ? PriceList::activasParaSelectCached() : collect(),
        ]);
    }
}
