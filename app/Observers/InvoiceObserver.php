<?php

namespace App\Observers;

use App\Enums\InvoiceStatus;
use App\Models\CompanySettings;
use App\Models\Invoice;
use App\Support\RemitoFacturador;

/**
 * Cuando un Remito de la tienda online (ver Livewire\Tienda\Checkout) queda
 * pagado, convierte el Remito en Factura — misma operación que ya hace
 * "Facturar remito" a mano, disparada sola porque acá no hay ningún staff
 * mirando la pantalla. A propósito NO pide el CAE a AFIP: eso sigue siendo
 * un clic manual del staff desde Invoices\Show (Emitir AFIP), igual que para
 * cualquier otra venta — si AFIP rechaza algo, alguien tiene que verlo y
 * corregirlo, no puede quedar silenciado en un log de un webhook público.
 */
class InvoiceObserver
{
    public function updated(Invoice $invoice): void
    {
        if (! $invoice->wasChanged('status') || $invoice->status !== InvoiceStatus::Paid) {
            return;
        }

        if ($invoice->tienda_pedido_id === null || ! $invoice->esRemito()) {
            return;
        }

        $pedido = $invoice->tiendaPedido;

        // Idempotente: MercadoPagoPaymentApplier::apply() puede llamarse más
        // de una vez para el mismo pago (webhook + polling, o reintentos de
        // MP) — si ya se facturó/marcó pagado este pedido, no hacer nada.
        if (! $pedido || $pedido->status === 'paid') {
            return;
        }

        $tipo = CompanySettings::current()->tipoComprobantePorDefecto();

        if ($tipo->esFiscal()) {
            RemitoFacturador::facturar($invoice, $tipo);
        }

        $pedido->update(['status' => 'paid']);
    }
}
