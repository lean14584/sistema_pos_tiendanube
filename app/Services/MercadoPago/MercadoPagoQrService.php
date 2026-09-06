<?php

namespace App\Services\MercadoPago;

use App\Models\Invoice;
use App\Models\SucursalMercadoPagoConfig;
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
 * Cada sucursal puede tener su propia cuenta de Mercado Pago (ver ABM en
 * Sucursales\Edit, tabla `sucursal_mercadopago_configs`). Si una sucursal no
 * tiene nada configurado, se usa el token global de `config('mercadopago')`
 * (`.env`) — así un comercio de una sola sucursal sigue funcionando sin tener
 * que cargar nada en el ABM.
 *
 * Doc: https://www.mercadopago.com.ar/developers/es/docs/qr-code/integration-configuration
 */
class MercadoPagoQrService
{
    public function isConfigured(?int $sucursalId = null): bool
    {
        return ! empty($this->configFor($sucursalId)['access_token']);
    }

    /**
     * @return array<string, mixed>
     */
    private function configFor(?int $sucursalId): array
    {
        $config = [
            'access_token' => config('mercadopago.access_token'),
            'base_url' => config('mercadopago.base_url'),
            'store_external_id' => config('mercadopago.store_external_id'),
            'pos_external_id' => config('mercadopago.pos_external_id'),
            'store_name' => config('mercadopago.store_name'),
            'pos_name' => config('mercadopago.pos_name'),
            'store_street' => config('mercadopago.store_street'),
            'store_number' => config('mercadopago.store_number'),
            'store_city' => config('mercadopago.store_city'),
            'store_state' => config('mercadopago.store_state'),
            'store_lat' => config('mercadopago.store_lat'),
            'store_lng' => config('mercadopago.store_lng'),
            'category' => config('mercadopago.category'),
            'notification_url' => config('mercadopago.notification_url'),
        ];

        if ($sucursalId === null) {
            return $config;
        }

        $row = SucursalMercadoPagoConfig::where('sucursal_id', $sucursalId)->first();

        if (! $row || empty($row->access_token)) {
            return $config;
        }

        foreach ($config as $key => $value) {
            if ($key === 'base_url') {
                continue; // la URL de la API de MP es la misma para todas las cuentas.
            }

            if ($row->{$key} !== null && $row->{$key} !== '') {
                $config[$key] = $row->{$key};
            }
        }

        return $config;
    }

    private function http(?int $sucursalId): PendingRequest
    {
        $config = $this->configFor($sucursalId);

        if (empty($config['access_token'])) {
            throw new RuntimeException($sucursalId
                ? 'Falta configurar el Access Token de Mercado Pago para esta sucursal (o el MP_ACCESS_TOKEN global).'
                : 'Falta configurar MP_ACCESS_TOKEN en el .env');
        }

        return Http::baseUrl($config['base_url'])
            ->withToken($config['access_token'])
            ->acceptJson()
            ->timeout(15);
    }

    /**
     * ID del vendedor (collector) dueño del access token. Se cachea porque
     * no cambia y se necesita en casi todos los endpoints de Instore. Si es
     * para una sucursal con config propia, además se persiste en
     * `sucursal_mercadopago_configs.collector_id` para poder resolver, dado
     * el `user_id` de un webhook, de qué sucursal es (ver
     * MercadoPagoWebhookController).
     */
    public function collectorId(?int $sucursalId = null): int
    {
        $config = $this->configFor($sucursalId);
        $token = $config['access_token'];

        return Cache::remember('mp:collector_id:'.md5((string) $token), now()->addDay(), function () use ($token, $sucursalId) {
            $res = Http::baseUrl(config('mercadopago.base_url'))->withToken($token)->acceptJson()->timeout(15)->get('/users/me');
            $res->throw();
            $id = (int) $res->json('id');

            if ($sucursalId !== null) {
                SucursalMercadoPagoConfig::where('sucursal_id', $sucursalId)->update(['collector_id' => $id]);
            }

            return $id;
        });
    }

    /** Dado el `user_id` (collector) que manda un webhook, a qué sucursal corresponde (null = ninguna con config propia, usar el token global). */
    public function resolveSucursalByCollectorId(int $collectorId): ?int
    {
        return SucursalMercadoPagoConfig::where('collector_id', $collectorId)->value('sucursal_id');
    }

    /**
     * Garantiza que exista la sucursal y la caja. Devuelve los datos de la
     * caja, incluida la imagen del QR fijo para imprimir. Idempotente: si ya
     * existen, no las duplica.
     *
     * @return array{store_id:int, pos_id:int, qr_image:?string, qr_template:?string}
     */
    public function ensureStoreAndPos(?int $sucursalId = null): array
    {
        $config = $this->configFor($sucursalId);
        $collector = $this->collectorId($sucursalId);

        // --- Sucursal ---
        $storeId = $this->findStoreId($sucursalId, $collector, $config);

        if ($storeId === null) {
            $res = $this->http($sucursalId)->post("/users/{$collector}/stores", [
                'name' => $config['store_name'],
                'external_id' => $config['store_external_id'],
                'location' => [
                    'street_number' => (string) $config['store_number'],
                    'street_name' => $config['store_street'],
                    'city_name' => $config['store_city'],
                    'state_name' => $config['store_state'],
                    'latitude' => $config['store_lat'],
                    'longitude' => $config['store_lng'],
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
        $pos = $this->findPos($sucursalId, $config);

        if ($pos === null) {
            $pos = $this->createPosWithRetry($sucursalId, $storeId, $config);
        }

        return [
            'store_id' => $storeId,
            'pos_id' => (int) ($pos['id'] ?? 0),
            'qr_image' => $pos['qr']['image'] ?? null,
            'qr_template' => $pos['qr']['template_document'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string,mixed>
     */
    private function createPosWithRetry(?int $sucursalId, int $storeId, array $config): array
    {
        $body = [
            'name' => $config['pos_name'],
            'fixed_amount' => false,
            'store_id' => $storeId,
            'external_store_id' => $config['store_external_id'],
            'external_id' => $config['pos_external_id'],
            'category' => $config['category'],
        ];

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $res = $this->http($sucursalId)->post('/pos', $body);

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

    /**
     * @param  array<string, mixed>  $config
     */
    private function findStoreId(?int $sucursalId, int $collector, array $config): ?int
    {
        $res = $this->http($sucursalId)->get("/users/{$collector}/stores/search", [
            'external_id' => $config['store_external_id'],
        ]);

        if ($res->failed()) {
            return null;
        }

        $results = $res->json('results', []);

        return isset($results[0]['id']) ? (int) $results[0]['id'] : null;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string,mixed>|null
     */
    private function findPos(?int $sucursalId, array $config): ?array
    {
        $res = $this->http($sucursalId)->get('/pos/search', [
            'external_id' => $config['pos_external_id'],
        ]);

        if ($res->failed()) {
            return null;
        }

        $results = $res->json('results', []);

        return $results[0] ?? null;
    }

    /**
     * Empuja el monto de una factura a la caja. Después de esto, el cliente
     * que escanee el QR de la pared ve el importe ya cargado. Usa la
     * sucursal de la factura (ver Fase 5 de multisucursal) para elegir con
     * qué cuenta de Mercado Pago cobrar.
     *
     * Devuelve el external_reference generado (se guarda en la factura para
     * poder consultar el estado después).
     */
    public function createOrder(Invoice $invoice): string
    {
        $sucursalId = $invoice->sucursal_id;
        $config = $this->configFor($sucursalId);
        $collector = $this->collectorId($sucursalId);
        $pos = $config['pos_external_id'];

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

        if ($url = $config['notification_url']) {
            $payload['notification_url'] = $url;
        }

        $res = $this->http($sucursalId)->put(
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
    public function paymentStatus(string $externalReference, ?int $sucursalId = null): string
    {
        $res = $this->http($sucursalId)->get('/merchant_orders/search', [
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
    public function merchantOrderPaidReference(string $orderId, ?int $sucursalId = null): ?string
    {
        $res = $this->http($sucursalId)->get("/merchant_orders/{$orderId}");

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
    public function paymentPaidReference(string $paymentId, ?int $sucursalId = null): ?string
    {
        $res = $this->http($sucursalId)->get("/v1/payments/{$paymentId}");

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
    public function cancelOrder(?int $sucursalId = null): void
    {
        $config = $this->configFor($sucursalId);
        $collector = $this->collectorId($sucursalId);
        $pos = $config['pos_external_id'];

        $this->http($sucursalId)->delete(
            "/instore/qr/seller/collectors/{$collector}/pos/{$pos}/orders"
        );
    }
}
