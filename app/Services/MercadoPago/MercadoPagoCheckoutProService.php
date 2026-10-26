<?php

namespace App\Services\MercadoPago;

use App\Models\Invoice;
use App\Models\SucursalMercadoPagoConfig;
use Illuminate\Support\Facades\Http;

/**
 * Checkout Pro de Mercado Pago (redirect a una página de pago hospedada por
 * MP) — a diferencia de MercadoPagoQrService (QR fijo presencial), esto es
 * para el pago a distancia de un pedido de la tienda online (ver
 * Livewire\Tienda\Checkout). Usa el mismo access_token por sucursal que el
 * QR (tabla sucursal_mercadopago_configs, con fallback al token global de
 * config('mercadopago')) y el MISMO webhook público /mp/webhook ya validado
 * por firma (ver MercadoPagoWebhookController) — el pago se detecta porque
 * la preferencia se crea con external_reference = TiendaPedido::token, igual
 * que Invoice::mp_external_reference.
 */
class MercadoPagoCheckoutProService
{
    public function isConfigured(?int $sucursalId): bool
    {
        return ! empty($this->accessToken($sucursalId));
    }

    private function accessToken(?int $sucursalId): ?string
    {
        if ($sucursalId !== null) {
            $own = SucursalMercadoPagoConfig::where('sucursal_id', $sucursalId)->value('access_token');

            if (! empty($own)) {
                return $own;
            }
        }

        return config('mercadopago.access_token');
    }

    /**
     * Crea la preferencia de pago para esta factura (Remito pendiente) y
     * devuelve la URL a la que redirigir al cliente.
     *
     * @return array{id: string, init_point: string}
     */
    public function createPreference(Invoice $invoice, string $externalReference, string $payerEmail): array
    {
        $token = $this->accessToken($invoice->sucursal_id);

        if (empty($token)) {
            throw new \RuntimeException('Mercado Pago no está configurado para esta tienda.');
        }

        $successUrl = route('tienda.order-status', $externalReference);

        $payload = [
            'external_reference' => $externalReference,
            'items' => $invoice->items->map(fn ($item) => [
                'title' => $item->description,
                'quantity' => (int) $item->quantity,
                'unit_price' => round((float) $item->unit_price, 2),
                'currency_id' => 'ARS',
            ])->all(),
            'payer' => ['email' => $payerEmail],
            'back_urls' => [
                'success' => $successUrl,
                'pending' => $successUrl,
                'failure' => $successUrl,
            ],
            'auto_return' => 'approved',
            'notification_url' => route('mp.webhook'),
            'statement_descriptor' => config('app.name'),
        ];

        $res = Http::baseUrl(config('mercadopago.base_url'))
            ->withToken($token)
            ->acceptJson()
            ->timeout(15)
            ->post('/checkout/preferences', $payload);

        $res->throw();

        return [
            'id' => (string) $res->json('id'),
            'init_point' => (string) $res->json('init_point'),
        ];
    }
}
