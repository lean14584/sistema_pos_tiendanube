<?php

namespace App\Livewire\Invoices;

use App\Enums\InvoiceStatus;
use App\Exceptions\Afip\AfipConnectionException;
use App\Exceptions\Afip\AfipRejectedException;
use App\Exceptions\Afip\AfipValidationException;
use App\Models\Invoice;
use App\Services\Afip\InvoiceCaeEmitter;
use App\Support\CashLinker;
use App\Support\StockAdjuster;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Invoice $invoice;

    public ?string $afipError = null;

    public function mount(Invoice $invoice): void
    {
        $this->invoice = $invoice;
    }

    public function setStatus(string $status): void
    {
        if ($this->invoice->isFiscal && $status === InvoiceStatus::Draft->value) {
            $this->addError('status', 'No se puede volver a borrador una factura que ya tiene CAE.');

            return;
        }

        $this->invoice->update(['status' => $status]);
    }

    public function emitirAfip(): void
    {
        $this->afipError = null;

        try {
            $this->invoice = app(InvoiceCaeEmitter::class)->emit($this->invoice);
        } catch (AfipRejectedException $e) {
            $this->afipError = $e->getMessage();
        } catch (AfipValidationException $e) {
            $this->afipError = $e->getMessage();
        } catch (AfipConnectionException $e) {
            $this->afipError = 'No se pudo contactar a AFIP, reintentá en unos minutos.';
        }
    }

    public function delete(): void
    {
        abort_if($this->invoice->isFiscal, 403, 'No se puede eliminar una factura con CAE. Emití una Nota de Crédito.');

        DB::transaction(function () {
            $items = $this->invoice->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'quantity' => (float) $item->quantity,
            ])->all();
            $sign = $this->invoice->afecta_stock ? $this->invoice->tipo_comprobante_interno->stockSign() : 0;
            StockAdjuster::apply($items, -$sign);

            $this->invoice->payments->each(fn ($payment) => CashLinker::unlinkInvoicePayment($payment));

            $this->invoice->delete();
        });

        $this->redirect(route('invoices.index'), navigate: true);
    }

    public function render()
    {
        $this->invoice->load('client', 'items', 'payments', 'relatedInvoice', 'creditNotes');

        return view('livewire.invoices.show', [
            'statuses' => InvoiceStatus::cases(),
        ]);
    }
}
