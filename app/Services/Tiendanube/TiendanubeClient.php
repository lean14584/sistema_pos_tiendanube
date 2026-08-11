<?php

namespace App\Services\Tiendanube;

use App\Models\CompanySettings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente HTTP de la API de Tiendanube (Nuvemshop).
 *
 * Ojo con dos particularidades de esta API:
 *  - El header de auth se llama "Authentication" (no "Authorization").
 *  - Exige un User-Agent con un email de contacto o rechaza las llamadas.
 *
 * Doc: https://tiendanube.github.io/api-documentation/
 */
class TiendanubeClient
{
    public function isConfigured(): bool
    {
        $s = CompanySettings::current();

        return ! empty($s->tiendanube_store_id) && ! empty($s->tiendanube_token);
    }

    private function http(): PendingRequest
    {
        $s = CompanySettings::current();

        if (empty($s->tiendanube_store_id) || empty($s->tiendanube_token)) {
            throw new RuntimeException('Falta configurar la conexión con Tiendanube.');
        }

        return Http::baseUrl(config('tiendanube.base_url').'/'.$s->tiendanube_store_id)
            ->withHeaders([
                'Authentication' => 'bearer '.$s->tiendanube_token,
                'User-Agent' => config('tiendanube.user_agent'),
                'Content-Type' => 'application/json',
            ])
            ->acceptJson()
            ->timeout(20);
    }

    /**
     * Datos de la tienda. Se usa para "Probar conexión".
     *
     * @return array<string,mixed>
     */
    public function getStore(): array
    {
        $res = $this->http()->get('/store');
        $res->throw();

        return $res->json();
    }

    /**
     * Una página de productos.
     *
     * @return array<int, array<string,mixed>>
     */
    public function getProducts(int $page = 1): array
    {
        $res = $this->http()->get('/products', [
            'page' => $page,
            'per_page' => config('tiendanube.per_page'),
        ]);
        $res->throw();

        return $res->json() ?? [];
    }

    /**
     * Una página de pedidos.
     *
     * @param  array<string,mixed>  $filtros
     * @return array<int, array<string,mixed>>
     */
    public function getOrders(int $page = 1, array $filtros = []): array
    {
        $res = $this->http()->get('/orders', array_merge([
            'page' => $page,
            'per_page' => config('tiendanube.per_page'),
        ], $filtros));
        $res->throw();

        return $res->json() ?? [];
    }

    /**
     * Actualiza el stock de una variante en Tiendanube.
     */
    public function updateVariantStock(int $productId, int $variantId, int $stock): void
    {
        $res = $this->http()->put("/products/{$productId}/variants/{$variantId}", [
            'stock' => $stock,
        ]);
        $res->throw();
    }

    /**
     * Crea un producto en Tiendanube. Devuelve el producto creado (con id y
     * variantes) para poder guardar el vínculo localmente.
     *
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function createProduct(array $payload): array
    {
        $res = $this->http()->post('/products', $payload);
        $res->throw();

        return $res->json();
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function updateProduct(int $productId, array $payload): void
    {
        $res = $this->http()->put("/products/{$productId}", $payload);
        $res->throw();
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function updateVariant(int $productId, int $variantId, array $payload): void
    {
        $res = $this->http()->put("/products/{$productId}/variants/{$variantId}", $payload);
        $res->throw();
    }

    /**
     * Un único producto (se usa al procesar un webhook de producto).
     *
     * @return array<string,mixed>
     */
    public function getProduct(int $productId): array
    {
        $res = $this->http()->get("/products/{$productId}");
        $res->throw();

        return $res->json();
    }

    /**
     * Un único pedido (se usa al procesar un webhook de orden).
     *
     * @return array<string,mixed>
     */
    public function getOrder(int $orderId): array
    {
        $res = $this->http()->get("/orders/{$orderId}");
        $res->throw();

        return $res->json();
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function getCustomers(int $page = 1): array
    {
        $res = $this->http()->get('/customers', [
            'page' => $page,
            'per_page' => config('tiendanube.per_page'),
        ]);
        $res->throw();

        return $res->json() ?? [];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function createCustomer(array $payload): array
    {
        $res = $this->http()->post('/customers', $payload);
        $res->throw();

        return $res->json();
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function listWebhooks(): array
    {
        $res = $this->http()->get('/webhooks');
        $res->throw();

        return $res->json() ?? [];
    }

    public function createWebhook(string $event, string $url): void
    {
        $res = $this->http()->post('/webhooks', [
            'event' => $event,
            'url' => $url,
        ]);
        $res->throw();
    }

    public function deleteWebhook(int $id): void
    {
        $this->http()->delete("/webhooks/{$id}");
    }
}
