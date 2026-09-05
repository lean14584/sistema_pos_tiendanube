<?php

namespace App\Livewire\Invoices;

use App\Enums\InvoiceStatus;
use App\Enums\TipoComprobanteInterno;
use App\Exceptions\Afip\AfipConnectionException;
use App\Exceptions\Afip\AfipRejectedException;
use App\Exceptions\Afip\AfipValidationException;
use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Services\Afip\InvoiceCaeEmitter;
use App\Services\MercadoPago\MercadoPagoQrService;
use App\Services\TicketPrinterService;
use App\Support\CashLinker;
use App\Support\MercadoPagoPaymentApplier;
use App\Support\StockAdjuster;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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

        // Al salir de borrador la factura empieza a contar como deuda real
        // (ver Client::saldoCuentaCorriente), así que ahí es donde hay que
        // chequear el límite de crédito, igual que al crearla.
        $tipo = $this->invoice->tipo_comprobante_interno;
        if ($this->invoice->status === InvoiceStatus::Draft
            && $status !== InvoiceStatus::Draft->value
            && ! $tipo->esNotaCredito()
            && $tipo !== TipoComprobanteInterno::Devolucion) {
            $pendiente = round((float) $this->invoice->total - (float) $this->invoice->payments->sum('amount'), 2);
            $cliente = $this->invoice->client;

            if ($pendiente > 0.009 && $cliente && ($excesoMsg = $cliente->excesoDeCredito($pendiente))) {
                $this->addError('status', $excesoMsg);

                return;
            }
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
            $this->afipError = 'No se pudo contactar a ARCA, reintentá en unos minutos.';
        }
    }

    public function enviarPorEmail(): void
    {
        $cliente = $this->invoice->client;

        if (! $cliente || ! $cliente->email) {
            session()->flash('error', 'El cliente no tiene un email cargado.');

            return;
        }

        try {
            Mail::to($cliente->email)->send(new InvoiceMail($this->invoice));
            session()->flash('status', 'Factura enviada por email a '.$cliente->email.'.');
        } catch (\Throwable $e) {
            session()->flash('error', 'No se pudo enviar el email: '.$e->getMessage());
        }
    }

    /**
     * Link de WhatsApp con un mensaje ya escrito. Null si el cliente no tiene
     * teléfono. WhatsApp no permite adjuntar el PDF por link, así que va el
     * resumen; el PDF se manda por email o se descarga.
     */
    public function whatsappUrl(): ?string
    {
        $telefono = preg_replace('/\D/', '', (string) ($this->invoice->client->phone ?? ''));

        if ($telefono === '') {
            return null;
        }

        $mensaje = "Hola {$this->invoice->client->name}, te paso tu factura {$this->invoice->number} por un total de $".money((float) $this->invoice->total).'. ¡Gracias!';

        return 'https://wa.me/'.$telefono.'?text='.rawurlencode($mensaje);
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
        abort_if(
            $this->invoice->esRemito() && $this->invoice->facturaGenerada() !== null,
            403,
            'Este remito ya fue facturado, no se puede eliminar: eliminá o corregí la factura generada primero.'
        );

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

        session()->flash('status', 'Comprobante eliminado.');
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
