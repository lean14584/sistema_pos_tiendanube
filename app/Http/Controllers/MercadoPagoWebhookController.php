<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\MercadoPago\MercadoPagoQrService;
use App\Support\MercadoPagoPaymentApplier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Recibe las notificaciones de Mercado Pago cuando cambia el estado de un
 * pago. Confirma contra la API (no confía en el body a ciegas) y, si está
 * pagado, marca la factura correspondiente.
 *
 * Es opcional: el cobro también se detecta por polling desde la pantalla. Sirve
 * cuando el sistema tiene una URL pública (MP_NOTIFICATION_URL).
 */
class MercadoPagoWebhookController extends Controller
{
    public function __invoke(Request $request, MercadoPagoQrService $mp): Response
    {
        $type = $request->input('type', $request->input('topic'));
        $id = $request->input('data.id', $request->input('id'));

        if (! $id) {
            return response('ok', 200);
        }

        $reference = match ($type) {
            'payment' => $mp->paymentPaidReference((string) $id),
            'merchant_order' => $mp->merchantOrderPaidReference((string) $id),
            default => null,
        };

        if ($reference) {
            $invoice = Invoice::where('mp_external_reference', $reference)->first();

            if ($invoice) {
                MercadoPagoPaymentApplier::apply($invoice);
            }
        }

        // MP espera un 200/201 para dar por entregada la notificación.
        return response('ok', 200);
    }
}
