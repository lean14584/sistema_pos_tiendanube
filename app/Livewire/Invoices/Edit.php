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
    use ManagesInvoiceLines;

    public Invoice $invoice;

    public string $client_id = '';

    public ?int $price_list_id = null;

    public string $tipo_comprobante_interno = 'factura_b';

    public string $issue_date;

    public string $due_date;

    public string $tax_rate = '0';

    public string $notes = '';

    public string $status = 'draft';

    /** @var array<int, array{product_id: ?int, description: string, quantity: string, unit_price: string, discount: string}> */
    public array $items = [];

    /** @var array<int, array{method: string, amount: string}> */
    public array $payments = [];

    public string $productQuery = '';

    public string $clientQuery = '';

    public function mount(Invoice $invoice): void
    {
        abort_if($invoice->isFiscal, 403, 'Esta factura ya tiene CAE y no puede editarse.');
        abort_if(
            $invoice->esRemito() && $invoice->facturaGenerada() !== null,
            403,
            'Este remito ya fue facturado, no se puede editar: corregí la factura generada en su lugar.'
        );

        $this->invoice = $invoice;
        $this->client_id = (string) $invoice->client_id;
        $this->price_list_id = $invoice->client?->price_list_id; // null = precio base
        $this->tipo_comprobante_interno = $invoice->tipo_comprobante_interno->value;
        $this->issue_date = $invoice->issue_date->toDateString();
        $this->due_date = $invoice->due_date->toDateString();
        $this->tax_rate = (string) $invoice->tax_rate;
        $this->notes = (string) $invoice->notes;
        $this->status = $invoice->status->value;

        $this->items = $invoice->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'description' => $item->description,
            'quantity' => (string) $item->quantity,
            'unit_price' => (string) $item->unit_price,
            'discount' => (string) $item->discount_percent,
            'iva_rate' => AlicuotaIva::normalizar($item->iva_rate_efectiva),
        ])->all();

        $this->payments = $invoice->payments->map(fn ($payment) => [
            'method' => $payment->method->value,
            'amount' => (string) $payment->amount,
        ])->all();
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

        // Re-chequeo por si se emitió a AFIP desde otra pestaña mientras
        // esta seguía abierta con datos viejos.
        abort_if($this->invoice->fresh()->cae !== null, 403, 'Esta factura ya tiene CAE y no puede editarse.');

        $tipoNuevo = TipoComprobanteInterno::from($this->tipo_comprobante_interno);

        // Se permite un tipo deshabilitado sólo si ya era el de la factura
        // (para no bloquear la edición de un comprobante viejo); cambiarlo a
        // uno deshabilitado, no.
        $permitidos = CompanySettings::current()->tiposComprobanteSeleccionables();
        if ($tipoNuevo !== $this->invoice->tipo_comprobante_interno && ! in_array($tipoNuevo, $permitidos, true)) {
            $this->addError('tipo_comprobante_interno', 'Ese tipo de comprobante está deshabilitado en la configuración.');

            return;
        }

        DB::transaction(function () use ($validItems, $tipoNuevo) {
            $tipoViejo = $this->invoice->tipo_comprobante_interno;
            // Nunca se cambia desde este formulario (las Notas de Crédito
            // ni siquiera muestran el switch), pero sí hay que respetarlo al
            // revertir/reaplicar o una NC con "no repone stock" se rompería.
            $afectaStock = $this->invoice->afecta_stock;
            $itemsViejos = $this->invoice->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'quantity' => (float) $item->quantity,
            ])->all();
            StockAdjuster::apply($itemsViejos, $afectaStock ? -$tipoViejo->stockSign() : 0);

            $this->invoice->update([
                'client_id' => $this->client_id,
                'tipo_comprobante_interno' => $tipoNuevo,
                'issue_date' => $this->issue_date,
                'due_date' => $this->due_date,
                'tax_rate' => 0,
                'notes' => $this->notes ?: null,
                'status' => $this->status,
            ]);

            $this->invoice->items()->delete();
            foreach ($validItems as $item) {
                $this->invoice->items()->create([
                    'product_id' => $item['product_id'],
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount_percent' => $item['discount'] ?? 0,
                    'iva_rate' => $item['iva_rate'] ?? '21',
                ]);
            }

            StockAdjuster::apply($validItems, $afectaStock ? $tipoNuevo->stockSign() : 0);

            $this->invoice->payments->each(fn ($payment) => CashLinker::unlinkInvoicePayment($payment));
            $this->invoice->payments()->delete();

            if ($tipoNuevo !== TipoComprobanteInterno::RemitoX) {
                foreach ($this->payments as $payment) {
                    if ((float) $payment['amount'] > 0) {
                        $created = $this->invoice->payments()->create($payment);

                        $tipoNuevo === TipoComprobanteInterno::Devolucion || $tipoNuevo->esNotaCredito()
                            ? CashLinker::linkInvoiceRefund($this->invoice, $created)
                            : CashLinker::linkInvoicePayment($this->invoice, $created);
                    }
                }
            }
        });

        session()->flash('status', 'Comprobante actualizado.');
        $this->redirect(route('invoices.show', $this->invoice), navigate: true);
    }

    public function render()
    {
        // Los tipos habilitados, más el actual de la factura por si quedó
        // deshabilitado después de crearla (así se sigue viendo/pudiendo dejar).
        $opciones = CompanySettings::current()->tiposComprobanteSeleccionables();
        if (! in_array($this->invoice->tipo_comprobante_interno, $opciones, true)
            && in_array($this->invoice->tipo_comprobante_interno, TipoComprobanteInterno::seleccionablesEnFactura(), true)) {
            $opciones[] = $this->invoice->tipo_comprobante_interno;
        }

        return view('livewire.invoices.edit', [
            'clients' => Client::forSelectCached(),
            'statuses' => InvoiceStatus::cases(),
            'paymentMethods' => PaymentMethod::cases(),
            'tipoComprobanteInternoOptions' => $opciones,
            'priceLists' => PriceList::active()->orderBy('name')->get(),
            'esNotaCredito' => $this->invoice->related_invoice_id !== null,
        ]);
    }
}
