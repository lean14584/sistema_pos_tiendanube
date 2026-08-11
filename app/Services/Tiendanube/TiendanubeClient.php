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
}
