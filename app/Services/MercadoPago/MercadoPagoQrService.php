<?php

namespace App\Services\MercadoPago;

use App\Models\Invoice;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cobro con QR "Pedido" de Mercado Pago (Instore integrado).
 *
 * El QR es fijo: se crea una vez una sucursal (store) y una caja (POS), y el
 * QR de esa caja se imprime y se pega en la pared. Para cobrar, se le "empuja"
 * el monto a la caja (createOrder); el próximo cliente que escanea ve el importe
 * ya cargado. El pago se confirma por webhook o por polling (paymentStatus).
 *
 * Doc: https://www.mercadopago.com.ar/developers/es/docs/qr-code/integration-configuration
 */
class MercadoPagoQrService
{
    public function isConfigured(): bool
    {
        return ! empty(config('mercadopago.access_token'));
    }

    private function http(): PendingRequest
    {
        $token = config('mercadopago.access_token');

        if (empty($token)) {
            throw new RuntimeException('Falta configurar MP_ACCESS_TOKEN en el .env');
        }

        return Http::baseUrl(config('mercadopago.base_url'))
            ->withToken($token)
            ->acceptJson()
            ->timeout(15);
    }

    /**
     * ID del vendedor (collector) dueño del access token. Se cachea porque
     * no cambia y se necesita en casi todos los endpoints de Instore.
     */
    public function collectorId(): int
    {
        return Cache::remember('mp:collector_id:'.md5((string) config('mercadopago.access_token')), now()->addDay(), function () {
            $res = $this->http()->get('/users/me');
            $res->throw();

            return (int) $res->json('id');
        });
    }

    private function storeExternalId(): string
    {
        return (string) config('mercadopago.store_external_id');
    }

    private function posExternalId(): string
    {
        return (string) config('mercadopago.pos_external_id');
    }

    /**
     * Garantiza que exista la sucursal y la caja. Devuelve los datos de la
     * caja, incluida la imagen del QR fijo para imprimir. Idempotente: si ya
     * existen, no las duplica.
     *
     * @return array{store_id:int, pos_id:int, qr_image:?string, qr_template:?string}
     */
    public function ensureStoreAndPos(): array
    {
        $collector = $this->collectorId();

        // --- Sucursal ---
        $storeId = $this->findStoreId($collector);

        if ($storeId === null) {
            $res = $this->http()->post("/users/{$collector}/stores", [
                'name' => config('mercadopago.store_name'),
                'external_id' => $this->storeExternalId(),
                'location' => [
                    'street_number' => (string) config('mercadopago.store_number'),
                    'street_name' => config('mercadopago.store_street'),
                    'city_name' => config('mercadopago.store_city'),
                    'state_name' => config('mercadopago.store_state'),
                    'latitude' => config('mercadopago.store_lat'),
                    'longitude' => config('mercadopago.store_lng'),
                    'reference' => '',
                ],
            ]);
            $res->throw();
            $storeId = (int) $res->json('id');

            // La sucursal recién creada tarda un instante en quedar visible
            // para el endpoint de cajas (consistencia eventual): si creamos la
            // caja en el mismo momento, MP responde non_existent_external_store_id.
            sleep(2);
        }

        // --- Caja (POS) ---
        $pos = $this->findPos();

        if ($pos === null) {
            $pos = $this->createPosWithRetry($storeId);
        }

        return [
            'store_id' => $storeId,
            'pos_id' => (int) ($pos['id'] ?? 0),
            'qr_image' => $pos['qr']['image'] ?? null,
            'qr_template' => $pos['qr']['template_document'] ?? null,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function createPosWithRetry(int $storeId): array
    {
        $body = [
            'name' => config('mercadopago.pos_name'),
            'fixed_amount' => false,
            'store_id' => $storeId,
            'external_store_id' => $this->storeExternalId(),
            'external_id' => $this->posExternalId(),
            'category' => config('mercadopago.category'),
        ];

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $res = $this->http()->post('/pos', $body);

            if ($res->successful()) {
                return $res->json();
            }

            // Reintenta sólo mientras la sucursal todavía no es visible.
            if ($res->json('error') === 'non_existent_external_store_id' && $attempt < 3) {
                sleep(2);

                continue;
            }

            $res->throw();
        }

        return [];
    }

    private function findStoreId(int $collector): ?int
    {
        $res = $this->http()->get("/users/{$collector}/stores/search", [
            'external_id' => $this->storeExternalId(),
        ]);

        if ($res->failed()) {
            return null;
        }

        $results = $res->json('results', []);

        return isset($results[0]['id']) ? (int) $results[0]['id'] : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function findPos(): ?array
    {
        $res = $this->http()->get('/pos/search', [
            'external_id' => $this->posExternalId(),
        ]);

        if ($res->failed()) {
            return null;
        }

        $results = $res->json('results', []);

        return $results[0] ?? null;
    }

    /**
     * Empuja el monto de una factura a la caja. Después de esto, el cliente
     * que escanee el QR de la pared ve el importe ya cargado.
     *
     * Devuelve el external_reference generado (se guarda en la factura para
     * poder consultar el estado después).
     */
    public function createOrder(Invoice $invoice): string
    {
        $collector = $this->collectorId();
        $pos = $this->posExternalId();

        $reference = 'INV-'.$invoice->id.'-'.now()->timestamp;

        $payload = [
            'external_reference' => $reference,
            'title' => 'Factura '.$invoice->number,
            'description' => 'Cobro factura '.$invoice->number,
            'total_amount' => round((float) $invoice->total, 2),
            'items' => [[
                'title' => 'Factura '.$invoice->number,
                'unit_price' => round((float) $invoice->total, 2),
                'quantity' => 1,
                'unit_measure' => 'unit',
                'total_amount' => round((float) $invoice->total, 2),
            ]],
        ];

        if ($url = config('mercadopago.notification_url')) {
            $payload['notification_url'] = $url;
        }

        $res = $this->http()->put(
            "/instore/qr/seller/collectors/{$collector}/pos/{$pos}/orders",
            $payload
        );
        $res->throw();

        return $reference;
    }

    /**
     * Estado del cobro contra Mercado Pago.
     *
     * @return string 'paid' | 'pending' | 'none'
     */
    public function paymentStatus(string $externalReference): string
    {
        $res = $this->http()->get('/merchant_orders/search', [
            'external_reference' => $externalReference,
        ]);

        if ($res->failed()) {
            return 'pending';
        }

        $orders = $res->json('elements', []);

        if (empty($orders)) {
            return 'none';
        }

        foreach ($orders as $order) {
            // order_status == 'paid' cuando el total pagado cubre el pedido.
            if (($order['order_status'] ?? null) === 'paid') {
                return 'paid';
            }

            foreach ($order['payments'] ?? [] as $payment) {
                if (($payment['status'] ?? null) === 'approved') {
                    return 'paid';
                }
            }
        }

        return 'pending';
    }

    /**
     * Dado el id de una merchant_order (que llega por webhook), devuelve el
     * external_reference si el pedido está pagado; null en caso contrario.
     */
    public function merchantOrderPaidReference(string $orderId): ?string
    {
        $res = $this->http()->get("/merchant_orders/{$orderId}");

        if ($res->failed()) {
            return null;
        }

        $paid = ($res->json('order_status') === 'paid')
            || collect($res->json('payments', []))->contains(fn ($p) => ($p['status'] ?? null) === 'approved');

        return $paid ? $res->json('external_reference') : null;
    }

    /**
     * Dado el id de un pago (webhook type=payment), devuelve el
     * external_reference si el pago fue aprobado; null en caso contrario.
     */
    public function paymentPaidReference(string $paymentId): ?string
    {
        $res = $this->http()->get("/v1/payments/{$paymentId}");

        if ($res->failed()) {
            return null;
        }

        return $res->json('status') === 'approved'
            ? $res->json('external_reference')
            : null;
    }

    /**
     * Borra el pedido cargado en la caja (por ejemplo si el cliente se
     * arrepiente antes de pagar). Deja la caja lista para el próximo cobro.
     */
    public function cancelOrder(): void
    {
        $collector = $this->collectorId();
        $pos = $this->posExternalId();

        $this->http()->delete(
            "/instore/qr/seller/collectors/{$collector}/pos/{$pos}/orders"
        );
    }
}
