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
 *
 * A diferencia de TiendanubeWebhookController (que ya validaba firma), esta
 * ruta no verificaba nada: cualquier POST con `type=payment&data.id=lo-que-sea`
 * disparaba una llamada bloqueante de hasta 15s a la API de MP
 * (MercadoPagoQrService::http() tiene timeout(15)) — un flood de requests
 * inventados mantenía ocupados los workers de PHP-FPM sin necesitar ninguna
 * credencial. Se agregó la misma validación de firma que documenta Mercado
 * Pago (header x-signature, HMAC-SHA256), fail-closed como Tiendanube: sin
 * secret configurado, se rechaza todo.
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

        // El body del webhook trae el `user_id` (collector) del vendedor de
        // MP que originó el evento. Sin resolver primero de qué sucursal es,
        // no hay forma de saber con qué access_token/secret validar el
        // evento — cada sucursal puede tener una cuenta de MP distinta (ver
        // MercadoPagoQrService::configFor). Si no matchea ninguna sucursal
        // con config propia, sucursalId queda null y se usa la global.
        $mpUserId = $request->input('user_id');
        $sucursalId = $mpUserId ? $mp->resolveSucursalByCollectorId((int) $mpUserId) : null;

        // Firma ANTES de la llamada cara a la API de MP (paymentPaidReference
        // hace un GET bloqueante de hasta 15s) — mismo criterio que
        // TiendanubeWebhookController::firmaValida(), que corta apenas puede.
        if (! $this->firmaValida($request, $mp, $sucursalId)) {
            return response('invalid signature', 401);
        }

        try {
            $reference = match ($type) {
                'payment' => $mp->paymentPaidReference((string) $id, $sucursalId),
                'merchant_order' => $mp->merchantOrderPaidReference((string) $id, $sucursalId),
                default => null,
            };

            if ($reference) {
                $invoice = Invoice::where('mp_external_reference', $reference)->first();

                if ($invoice) {
                    MercadoPagoPaymentApplier::apply($invoice);
                }
            }
        } catch (\Throwable $e) {
            // Ej. MP_ACCESS_TOKEN sin configurar (RuntimeException de
            // MercadoPagoQrService::http()) — no devolvemos 500 para no
            // generar reintentos en loop de MP por una instalación que
            // todavía no cargó el token; queda en el log. Mismo criterio que
            // TiendanubeWebhookController.
            report($e);
        }

        // MP espera un 200/201 para dar por entregada la notificación.
        return response('ok', 200);
    }

    /**
     * Algoritmo documentado por Mercado Pago: header `x-signature` con
     * formato "ts=<timestamp>,v1=<hash>", y el hash es HMAC-SHA256 (hex) de
     * un "manifest" armado como "id:<data.id>;request-id:<x-request-id>;ts:<ts>;".
     * El <data.id> tiene que salir del QUERY STRING crudo de la URL, no del
     * body — y hay que parsearlo a mano: PHP mangla los puntos de las claves
     * de query string a guiones bajos al armar $_GET (data.id → data_id), así
     * que ni $request->query() ni parse_str() nativo devuelven el valor
     * correcto para una clave con punto literal.
     */
    private function firmaValida(Request $request, MercadoPagoQrService $mp, ?int $sucursalId): bool
    {
        $secret = $mp->webhookSecretFor($sucursalId);

        if (empty($secret)) {
            return false;
        }

        [$ts, $v1] = $this->parseSignatureHeader($request->header('x-signature', ''));

        if ($ts === null || $v1 === null) {
            return false;
        }

        $dataId = $this->rawQueryParam($request, 'data.id') ?? $this->rawQueryParam($request, 'id') ?? '';
        $requestId = $request->header('x-request-id', '');

        $manifest = 'id:'.strtolower($dataId).';request-id:'.$requestId.';ts:'.$ts.';';
        $esperada = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($esperada, $v1);
    }

    /** @return array{0: ?string, 1: ?string} [ts, v1] */
    private function parseSignatureHeader(string $header): array
    {
        $partes = [];

        foreach (explode(',', $header) as $par) {
            [$clave, $valor] = array_pad(explode('=', trim($par), 2), 2, null);

            if ($clave !== null) {
                $partes[$clave] = $valor;
            }
        }

        return [$partes['ts'] ?? null, $partes['v1'] ?? null];
    }

    /** Lee una clave del query string SIN pasar por $_GET (PHP mangla los puntos de las claves a guiones bajos). */
    private function rawQueryParam(Request $request, string $key): ?string
    {
        $query = $request->server->get('QUERY_STRING', '');

        foreach (explode('&', $query) as $par) {
            [$clave, $valor] = array_pad(explode('=', $par, 2), 2, '');

            if (urldecode($clave) === $key) {
                return urldecode($valor);
            }
        }

        return null;
    }
}
