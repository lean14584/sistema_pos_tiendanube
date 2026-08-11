<?php

namespace App\Livewire\Invoices;

use App\Enums\InvoiceStatus;
use App\Exceptions\Afip\AfipConnectionException;
use App\Exceptions\Afip\AfipRejectedException;
use App\Exceptions\Afip\AfipValidationException;
use App\Models\Invoice;
use App\Services\Afip\InvoiceCaeEmitter;
use App\Services\MercadoPago\MercadoPagoQrService;
use App\Services\TicketPrinterService;
use App\Support\CashLinker;
use App\Support\MercadoPagoPaymentApplier;
use App\Support\StockAdjuster;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Invoice $invoice;

    public ?string $afipError = null;

    // --- Cobro con QR de Mercado Pago ---
    public bool $showQrModal = false;

    public ?string $qrImage = null;

    public string $qrState = 'idle'; // idle | waiting | paid | error

    public ?string $qrError = null;

    public function mount(Invoice $invoice): void
    {
        $this->invoice = $invoice;
    }

    public function mpConfigured(): bool
    {
        return app(MercadoPagoQrService::class)->isConfigured();
    }

    /**
     * Empuja el monto de la factura a la caja de Mercado Pago y abre el modal
     * con el QR. A partir de acá, quien escanee el QR de la pared ve el importe.
     */
    public function startQrCharge(): void
    {
        $this->qrError = null;
        $this->qrImage = null;

        if ($this->invoice->status === InvoiceStatus::Paid) {
            $this->qrError = 'La factura ya está pagada.';

            return;
        }

        $mp = app(MercadoPagoQrService::class);

        try {
            $reference = $mp->createOrder($this->invoice);
            $this->invoice->update(['mp_external_reference' => $reference]);

            $data = $mp->ensureStoreAndPos();
            $this->qrImage = $data['qr_image'];
            $this->qrState = 'waiting';
            $this->showQrModal = true;
        } catch (\Throwable $e) {
            $this->qrState = 'error';
            $this->qrError = 'No se pudo iniciar el cobro: '.$e->getMessage();
            $this->showQrModal = true;
        }
    }

    /**
     * La pantalla llama a esto cada pocos segundos mientras espera el pago.
     */
    public function pollQr(): void
    {
        if ($this->qrState !== 'waiting' || ! $this->invoice->mp_external_reference) {
            return;
        }

        $status = app(MercadoPagoQrService::class)->paymentStatus($this->invoice->mp_external_reference);

        if ($status === 'paid') {
            $this->markPaidFromQr();
        }
    }

    private function markPaidFromQr(): void
    {
        MercadoPagoPaymentApplier::apply($this->invoice);

        $this->qrState = 'paid';
    }

    public function cancelQrCharge(): void
    {
        try {
            app(MercadoPagoQrService::class)->cancelOrder();
        } catch (\Throwable $e) {
            // Si falla el borrado del pedido no bloqueamos el cierre del modal.
        }

        $this->closeQrModal();
    }

    public function closeQrModal(): void
    {
        $this->showQrModal = false;
        $this->qrState = 'idle';
        $this->qrImage = null;
        $this->qrError = null;
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

    public function printTicket(): void
    {
        try {
            app(TicketPrinterService::class)->imprimir($this->invoice);
            session()->flash('status', 'Ticket enviado a la impresora.');
        } catch (\Throwable $e) {
            session()->flash('error', 'No se pudo imprimir el ticket: '.$e->getMessage());
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
            'mpConfigured' => $this->mpConfigured(),
            'pollSeconds' => (int) config('mercadopago.poll_seconds', 3),
        ]);
    }
}
