<?php

namespace App\Http\Controllers;

use App\Models\CompanySettings;
use App\Services\Tiendanube\TiendanubeClient;
use App\Services\Tiendanube\TiendanubeSync;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Recibe las notificaciones de Tiendanube (sincronización automática).
 *
 * Tiendanube firma cada webhook con el client_secret de la app (HMAC-SHA256
 * sobre el cuerpo crudo, en el header x-linkedstore-hmac-sha256). Es una ruta
 * pública, así que sin secret configurado se rechaza todo (ver
 * Tiendanube\Index::enableWebhooks, que exige cargarlo antes de activar la
 * sincronización).
 */
class TiendanubeWebhookController extends Controller
{
    public function __invoke(Request $request, TiendanubeClient $client, TiendanubeSync $sync): Response
    {
        if (! $this->firmaValida($request)) {
            return response('invalid signature', 401);
        }

        $event = (string) $request->input('event');
        $id = (int) $request->input('id');

        if ($id === 0) {
            return response('ok', 200);
        }

        // El evento trae el recurso y el id: "order/paid" → pedido, "product/*"
        // → producto, "customer/*" → cliente, "category/*" → categoría.
        [$recurso] = explode('/', $event, 2) + [''];

        try {
            match ($recurso) {
                'order' => $sync->importOrder($client->getOrder($id)),
                'product' => $sync->updateStockFromTiendanube($id),
                'customer' => $sync->updateCustomerFromTiendanube($id),
                'category' => $sync->updateCategoryFromTiendanube($id),
                default => null,
            };
        } catch (\Throwable $e) {
            // No devolvemos 500 para que Tiendanube no reintente en loop por
            // un pedido puntual que no pudimos procesar; queda en el log.
            report($e);
        }

        return response('ok', 200);
    }

    private function firmaValida(Request $request): bool
    {
        $secret = CompanySettings::current()->tiendanube_webhook_secret;

        // Sin secret configurado no hay forma de validar la firma: se
        // rechaza (fail-closed). El endpoint es público, así que aceptar sin
        // firma dejaría a cualquiera disparar una resincronización.
        if (empty($secret)) {
            return false;
        }

        $firma = $request->header('x-linkedstore-hmac-sha256', '');
        $esperada = base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true));

        return hash_equals($esperada, $firma);
    }
}
