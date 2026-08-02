<?php

namespace App\Livewire\Invoices;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\TipoComprobanteInterno;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Support\CashLinker;
use App\Support\StockAdjuster;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Create extends Component
{
    public string $client_id = '';

    public string $tipo_comprobante_interno = 'factura_b';

    public string $issue_date;

    public string $due_date;

    public string $tax_rate = '0';

    public string $notes = '';

    public string $status = 'draft';

    /** @var array<int, array{product_id: ?int, description: string, quantity: string, unit_price: string}> */
    public array $items = [];

    /** @var array<int, array{method: string, amount: string}> */
    public array $payments = [];

    public string $productQuery = '';

    public function mount(): void
    {
        $this->issue_date = now()->toDateString();
        $this->due_date = now()->addDays(15)->toDateString();
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

    public function save(): void
    {
        $this->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'tipo_comprobante_interno' => ['required', Rule::enum(TipoComprobanteInterno::class)],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date'],
            'tax_rate' => ['required', 'numeric', 'min:0'],
            'status' => ['required'],
            'notes' => ['nullable', 'string'],
        ]);

        $validItems = collect($this->items)->filter(fn ($item) => trim($item['description']) !== '');

        if ($validItems->isEmpty()) {
            $this->addError('items', 'Agregá al menos un ítem con descripción.');

            return;
        }

        $tipo = TipoComprobanteInterno::from($this->tipo_comprobante_interno);

        $invoice = DB::transaction(function () use ($validItems, $tipo) {
            $invoice = Invoice::create([
                'number' => $this->nextNumber(),
                'client_id' => $this->client_id,
                'tipo_comprobante_interno' => $tipo,
                'issue_date' => $this->issue_date,
                'due_date' => $this->due_date,
                'tax_rate' => $this->tax_rate,
                'notes' => $this->notes ?: null,
                'status' => $this->status,
            ]);

            foreach ($validItems as $item) {
                $invoice->items()->create([
                    'product_id' => $item['product_id'],
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                ]);
            }

            StockAdjuster::apply($validItems, $tipo->stockSign());

            if ($tipo !== TipoComprobanteInterno::RemitoX) {
                foreach ($this->payments as $payment) {
                    if ((float) $payment['amount'] > 0) {
                        $created = $invoice->payments()->create($payment);

                        $tipo === TipoComprobanteInterno::Devolucion
                            ? CashLinker::linkInvoiceRefund($invoice, $created)
                            : CashLinker::linkInvoicePayment($invoice, $created);
                    }
                }
            }

            return $invoice;
        });

        $this->redirect(route('invoices.show', $invoice), navigate: true);
    }

    private function nextNumber(): string
    {
        $prefix = match ($this->tipo_comprobante_interno) {
            'remito_x' => 'REM',
            'devolucion' => 'DEV',
            default => 'FAC',
        };

        $count = Invoice::where('number', 'like', "{$prefix}-%")->count() + 1;

        return "{$prefix}-".str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    public function render()
    {
        return view('livewire.invoices.create', [
            'clients' => Client::orderBy('name')->get(),
            'statuses' => InvoiceStatus::cases(),
            'paymentMethods' => PaymentMethod::cases(),
            'tipoComprobanteInternoOptions' => TipoComprobanteInterno::seleccionablesEnFactura(),
            'esNotaCredito' => false,
        ]);
    }
}
