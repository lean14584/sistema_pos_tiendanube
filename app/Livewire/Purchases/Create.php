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
class Create extends Component
{
    public string $provider_id = '';

    public string $tipo_comprobante = '1';

    public string $punto_venta = '1';

    public string $numero_comprobante = '1';

    public string $issue_date;

    public string $due_date;

    public string $tax_rate = '0';

    public string $notes = '';

    public string $status = 'draft';

    /** @var array<int, array{product_id: int, description: string, quantity: string, unit_price: string}> */
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
            'provider_id' => ['required', 'exists:providers,id'],
            'tipo_comprobante' => ['required', Rule::enum(TipoComprobante::class)],
            'punto_venta' => ['required', 'integer', 'min:1', 'max:9999'],
            'numero_comprobante' => ['required', 'integer', 'min:1', 'max:99999999'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date'],
            'tax_rate' => ['required', 'numeric', 'min:0'],
            'status' => ['required'],
            'notes' => ['nullable', 'string'],
        ]);

        if (empty($this->items)) {
            $this->addError('items', 'Agregá al menos un producto.');

            return;
        }

        $purchase = DB::transaction(function () {
            $purchase = Purchase::create([
                'number' => $this->nextNumber(),
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

            foreach ($this->items as $item) {
                $purchase->items()->create($item);
            }

            StockAdjuster::apply($this->items, 1);

            foreach ($this->payments as $payment) {
                if ((float) $payment['amount'] > 0) {
                    $created = $purchase->payments()->create($payment);
                    CashLinker::linkPurchasePayment($purchase, $created);
                }
            }

            return $purchase;
        });

        $this->redirect(route('purchases.show', $purchase), navigate: true);
    }

    private function nextNumber(): string
    {
        $count = Purchase::count() + 1;

        return 'COM-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    public function render()
    {
        return view('livewire.purchases.create', [
            'providers' => Provider::orderBy('name')->get(),
            'statuses' => InvoiceStatus::cases(),
            'tiposComprobante' => TipoComprobante::cases(),
            'paymentMethods' => PaymentMethod::cases(),
        ]);
    }
}
