<?php

namespace App\Livewire\Invoices;

use App\Enums\AlicuotaIva;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\TipoComprobanteInterno;
use App\Livewire\Invoices\Concerns\ManagesInvoiceLines;
use App\Models\Client;
use App\Models\CompanySettings;
use App\Models\Invoice;
use App\Models\PriceList;
use App\Models\Product;
use App\Services\TicketPrinterService;
use App\Support\CashLinker;
use App\Support\InvoiceNumberGenerator;
use App\Support\StockAdjuster;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Create extends Component
{
    use ManagesInvoiceLines;

    public string $client_id = '';

    public ?int $price_list_id = null;

    public string $tipo_comprobante_interno = 'factura_b';

    public string $issue_date;

    public string $due_date;

    public string $tax_rate = '0';

    public string $notes = '';

    public string $status = 'draft';

    public bool $printOnSave = true;

    /** @var array<int, array{product_id: ?int, description: string, quantity: string, unit_price: string, discount: string}> */
    public array $items = [];

    /** @var array<int, array{method: string, amount: string}> */
    public array $payments = [];

    public string $productQuery = '';

    public string $clientQuery = '';

    public function mount(): void
    {
        $this->issue_date = now()->toDateString();
        $this->due_date = now()->addDays(15)->toDateString();
        $cf = Client::consumidorFinal();
        $this->client_id = (string) $cf->id;
        $this->price_list_id = $cf->price_list_id; // null = precio base
        // Arranca en un tipo que la empresa tenga habilitado (evita quedar
        // en Factura B cuando B está apagada, por ejemplo).
        $this->tipo_comprobante_interno = CompanySettings::current()->tipoComprobantePorDefecto()->value;
    }

    /** Lista de precios vigente. null = precio base (sin ajuste). */
    public function currentPriceList(): ?PriceList
    {
        return $this->price_list_id ? PriceList::find($this->price_list_id) : null;
    }

    /** Al cambiar de cliente, tomo su lista asignada (o precio base) y recalculo. */
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
            'items.*.discount' => ['nullable', 'numeric', 'between:0,100'],
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

        // Límite de crédito: solo comprobantes que generan deuda (no NC ni devolución).
        if (! $tipo->esNotaCredito() && $tipo !== TipoComprobanteInterno::Devolucion) {
            $pendiente = round($this->total() - $this->paidTotal(), 2);
            $cliente = Client::find($this->client_id);
            if ($pendiente > 0.009 && $cliente && ($excesoMsg = $cliente->excesoDeCredito($pendiente))) {
                $this->addError('client_id', $excesoMsg);

                return;
            }
        }

        $invoice = InvoiceNumberGenerator::withLock($tipo->value, fn () => DB::transaction(function () use ($validItems, $tipo) {
            $invoice = Invoice::create([
                'number' => InvoiceNumberGenerator::next($tipo->value),
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
                    'discount_percent' => $item['discount'] ?? 0,
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
        }));

        if ($this->printOnSave) {
            try {
                app(TicketPrinterService::class)->imprimir($invoice);
            } catch (\Throwable $e) {
                session()->flash('error', 'La factura se guardó, pero no se pudo imprimir el ticket: '.$e->getMessage());
            }
        }

        $this->redirect(route('invoices.show', $invoice), navigate: true);
    }

    public function render()
    {
        return view('livewire.invoices.create', [
            'clients' => Client::forSelectCached(),
            'statuses' => InvoiceStatus::cases(),
            'paymentMethods' => PaymentMethod::cases(),
            'tipoComprobanteInternoOptions' => CompanySettings::current()->tiposComprobanteSeleccionables(),
            'priceLists' => PriceList::active()->orderBy('name')->get(),
            'esNotaCredito' => false,
        ]);
    }
}
