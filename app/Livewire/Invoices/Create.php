<?php

namespace App\Livewire\Invoices;

use App\Enums\AlicuotaIva;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\TipoComprobanteInterno;
use App\Models\Client;
use App\Models\CompanySettings;
use App\Models\Invoice;
use App\Models\Product;
use App\Services\TicketPrinterService;
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

    public bool $printOnSave = true;

    /** @var array<int, array{product_id: ?int, description: string, quantity: string, unit_price: string}> */
    public array $items = [];

    /** @var array<int, array{method: string, amount: string}> */
    public array $payments = [];

    public string $productQuery = '';

    public function mount(): void
    {
        $this->issue_date = now()->toDateString();
        $this->due_date = now()->addDays(15)->toDateString();
        $this->client_id = (string) Client::consumidorFinal()->id;
        // Arranca en un tipo que la empresa tenga habilitado (evita quedar
        // en Factura B cuando B está apagada, por ejemplo).
        $this->tipo_comprobante_interno = CompanySettings::current()->tipoComprobantePorDefecto()->value;
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
        return collect($this->items)->sum(fn ($item) => (float) $item['quantity'] * (float) $item['unit_price']);
    }

    public function netoGravado(): float
    {
        return collect($this->items)
            ->filter(fn ($item) => (float) ($item['iva_rate'] ?? 0) > 0)
            ->sum(fn ($item) => (float) $item['quantity'] * (float) $item['unit_price']);
    }

    public function netoExento(): float
    {
        return collect($this->items)
            ->filter(fn ($item) => (float) ($item['iva_rate'] ?? 0) <= 0)
            ->sum(fn ($item) => (float) $item['quantity'] * (float) $item['unit_price']);
    }

    public function taxAmount(): float
    {
        return collect($this->items)->sum(
            fn ($item) => (float) $item['quantity'] * (float) $item['unit_price'] * ((float) ($item['iva_rate'] ?? 0) / 100)
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
                'iva' => $grupo->sum(fn ($item) => (float) $item['quantity'] * (float) $item['unit_price'] * ((float) $item['iva_rate'] / 100)),
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

    public function save(): void
    {
        $this->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'tipo_comprobante_interno' => ['required', Rule::enum(TipoComprobanteInterno::class)],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date'],
            'status' => ['required'],
            'notes' => ['nullable', 'string'],
            'items.*.iva_rate' => ['nullable', Rule::in(AlicuotaIva::valores())],
        ]);

        $validItems = collect($this->items)->filter(fn ($item) => trim($item['description']) !== '');

        if ($validItems->isEmpty()) {
            $this->addError('items', 'Agregá al menos un ítem con descripción.');

            return;
        }

        $tipo = TipoComprobanteInterno::from($this->tipo_comprobante_interno);

        if (! in_array($tipo, CompanySettings::current()->tiposComprobanteSeleccionables(), true)) {
            $this->addError('tipo_comprobante_interno', 'Ese tipo de comprobante está deshabilitado en la configuración.');

            return;
        }

        $invoice = DB::transaction(function () use ($validItems, $tipo) {
            $invoice = Invoice::create([
                'number' => $this->nextNumber(),
                'client_id' => $this->client_id,
                'tipo_comprobante_interno' => $tipo,
                'issue_date' => $this->issue_date,
                'due_date' => $this->due_date,
                // El IVA ahora vive por ítem; se deja en 0 a nivel comprobante.
                'tax_rate' => 0,
                'notes' => $this->notes ?: null,
                'status' => $this->status,
            ]);

            foreach ($validItems as $item) {
                $invoice->items()->create([
                    'product_id' => $item['product_id'],
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'iva_rate' => $item['iva_rate'] ?? '21',
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

        if ($this->printOnSave) {
            try {
                app(TicketPrinterService::class)->imprimir($invoice);
            } catch (\Throwable $e) {
                session()->flash('error', 'La factura se guardó, pero no se pudo imprimir el ticket: '.$e->getMessage());
            }
        }

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
            'tipoComprobanteInternoOptions' => CompanySettings::current()->tiposComprobanteSeleccionables(),
            'esNotaCredito' => false,
        ]);
    }
}
