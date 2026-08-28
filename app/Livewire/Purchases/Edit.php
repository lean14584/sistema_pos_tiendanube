<?php

namespace App\Livewire\Purchases;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\TipoComprobante;
use App\Models\Product;
use App\Models\Provider;
use App\Models\Purchase;
use App\Support\CashLinker;
use App\Support\StockAdjuster;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Edit extends Component
{
    public Purchase $purchase;

    public string $provider_id = '';

    public string $tipo_comprobante = '1';

    public string $punto_venta = '1';

    public string $numero_comprobante = '';

    public string $issue_date;

    public string $due_date;

    public string $tax_rate = '0';

    public string $notes = '';

    public string $status = 'draft';

    /** @var array<int, array{product_id: int, description: string, quantity: string, unit_price: string}> */
    public array $items = [];

    /** @var array<int, array{method: string, amount: string}> */
    public array $payments = [];

    /** @var array<int, array{concepto: string, amount: string}> */
    public array $taxes = [];

    public string $productQuery = '';

    public function mount(Purchase $purchase): void
    {
        $this->purchase = $purchase;
        $this->provider_id = (string) $purchase->provider_id;
        $this->tipo_comprobante = (string) ($purchase->tipo_comprobante?->value ?? TipoComprobante::FacturaA->value);
        $this->punto_venta = (string) ($purchase->punto_venta ?? 1);
        $this->numero_comprobante = (string) ($purchase->numero_comprobante ?? 1);
        $this->issue_date = $purchase->issue_date->toDateString();
        $this->due_date = $purchase->due_date->toDateString();
        $this->tax_rate = (string) $purchase->tax_rate;
        $this->notes = (string) $purchase->notes;
        $this->status = $purchase->status->value;

        $this->items = $purchase->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'description' => $item->description,
            'quantity' => (string) $item->quantity,
            'unit_price' => (string) $item->unit_price,
        ])->all();

        $this->payments = $purchase->payments->map(fn ($payment) => [
            'method' => $payment->method->value,
            'amount' => (string) $payment->amount,
        ])->all();

        $this->taxes = $purchase->taxes->map(fn ($tax) => [
            'concepto' => $tax->concepto,
            'amount' => (string) $tax->amount,
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

    public function percepcionesTotal(): float
    {
        return collect($this->taxes)->sum(fn ($t) => (float) ($t['amount'] ?? 0));
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
        return $this->subtotal() + $this->taxAmount() + $this->percepcionesTotal();
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
            'provider_id' => ['required', 'exists:providers,id'],
            'tipo_comprobante' => ['required', Rule::enum(TipoComprobante::class)],
            'punto_venta' => ['required', 'integer', 'min:1', 'max:9999'],
            'numero_comprobante' => ['required', 'integer', 'min:1', 'max:99999999'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date'],
            'tax_rate' => ['required', 'numeric', 'min:0'],
            'status' => ['required'],
            'notes' => ['nullable', 'string'],
            'taxes.*.concepto' => ['required_with:taxes.*.amount', 'nullable', 'string', 'max:100'],
            'taxes.*.amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (empty($this->items)) {
            $this->addError('items', 'Agregá al menos un producto.');

            return;
        }

        DB::transaction(function () {
            // Reverse the stock impact of the items as they were before this edit.
            $previousItems = $this->purchase->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'quantity' => (float) $item->quantity,
            ])->all();
            StockAdjuster::apply($previousItems, -1);

            $this->purchase->update([
                'provider_id' => $this->provider_id,
                'tipo_comprobante' => $this->tipo_comprobante,
                'punto_venta' => $this->punto_venta,
                'numero_comprobante' => $this->numero_comprobante,
                'issue_date' => $this->issue_date,
                'due_date' => $this->due_date,
                'tax_rate' => $this->tax_rate,
                'notes' => $this->notes ?: null,
                'status' => $this->status,
            ]);

            $this->purchase->items()->delete();
            foreach ($this->items as $item) {
                $this->purchase->items()->create($item);
            }

            $this->purchase->taxes()->delete();
            foreach ($this->taxes as $tax) {
                if (trim((string) $tax['concepto']) !== '' && (float) $tax['amount'] > 0) {
                    $this->purchase->taxes()->create([
                        'concepto' => trim($tax['concepto']),
                        'amount' => $tax['amount'],
                    ]);
                }
            }

            StockAdjuster::apply($this->items, 1);

            $this->purchase->payments->each(fn ($payment) => CashLinker::unlinkPurchasePayment($payment));
            $this->purchase->payments()->delete();

            foreach ($this->payments as $payment) {
                if ((float) $payment['amount'] > 0) {
                    $created = $this->purchase->payments()->create($payment);
                    CashLinker::linkPurchasePayment($this->purchase, $created);
                }
            }
        });

        session()->flash('status', 'Compra actualizada.');
        $this->redirect(route('purchases.show', $this->purchase), navigate: true);
    }

    public function render()
    {
        return view('livewire.purchases.edit', [
            'providers' => Provider::orderBy('name')->get(),
            'statuses' => InvoiceStatus::cases(),
            'tiposComprobante' => TipoComprobante::cases(),
            'paymentMethods' => PaymentMethod::cases(),
        ]);
    }
}
