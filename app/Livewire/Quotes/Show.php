<?php

namespace App\Livewire\Quotes;

use App\Enums\QuoteStatus;
use App\Enums\TipoComprobanteInterno;
use App\Models\CompanySettings;
use App\Models\Invoice;
use App\Models\Quote;
use App\Support\InvoiceNumberGenerator;
use App\Support\StockAdjuster;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Quote $quote;

    public string $priceMode = 'keep';

    public function mount(Quote $quote): void
    {
        $this->quote = $quote;
    }

    public function setStatus(string $status): void
    {
        $this->quote->update(['status' => $status]);
    }

    public function delete(): void
    {
        $this->quote->delete();

        session()->flash('status', 'Presupuesto eliminado.');
        $this->redirect(route('quotes.index'), navigate: true);
    }

    public function convertToInvoice(): void
    {
        // Evita duplicar la venta si se llega a hacer doble clic.
        if ($this->quote->status === QuoteStatus::Converted) {
            return;
        }

        $updatePrices = $this->priceMode === 'update';

        // Mismo criterio que FacturarRemito: la fiscal habilitada preferida,
        // o Factura B si no hay ninguna (nunca Remito/Devolución acá).
        $default = CompanySettings::current()->tipoComprobantePorDefecto();
        $tipo = $default->esFiscal() ? $default : TipoComprobanteInterno::FacturaB;

        $invoice = InvoiceNumberGenerator::withLock($tipo->value, fn () => DB::transaction(function () use ($updatePrices, $tipo) {
            $invoice = Invoice::create([
                'number' => InvoiceNumberGenerator::next($tipo->value),
                'client_id' => $this->quote->client_id,
                'tipo_comprobante_interno' => $tipo,
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(15)->toDateString(),
                'tax_rate' => $this->quote->tax_rate,
                'notes' => $this->quote->notes,
                'status' => 'draft',
            ]);

            $stockItems = [];

            foreach ($this->quote->items as $item) {
                $product = $item->product_id ? $item->product : null;

                $data = ($updatePrices && $product)
                    ? [
                        'product_id' => $product->id,
                        'description' => $product->name,
                        'quantity' => $item->quantity,
                        'unit_price' => $product->price,
                    ]
                    : [
                        'product_id' => $item->product_id,
                        'description' => $item->description,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                    ];

                $invoice->items()->create($data);
                $stockItems[] = $data;
            }

            StockAdjuster::apply($stockItems, $tipo->stockSign());

            $this->quote->update([
                'status' => QuoteStatus::Converted,
                'converted_invoice_id' => $invoice->id,
            ]);

            return $invoice;
        }));

        $this->redirect(route('invoices.show', $invoice), navigate: true);
    }

    public function render()
    {
        $this->quote->load('client', 'items.product');

        return view('livewire.quotes.show', [
            'editableStatuses' => QuoteStatus::editable(),
        ]);
    }
}
