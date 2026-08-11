<?php

namespace App\Support;

use App\Jobs\PushToTiendanubeJob;
use App\Services\Tiendanube\TiendanubeClient;
use Illuminate\Database\Eloquent\Model;

/**
 * Punto único donde los observers deciden si empujar un cambio a Tiendanube.
 *
 * Solo encola si la integración está configurada y si el cambio lo hizo el
 * usuario (no un dato que vino DE Tiendanube, que llega silenciado por
 * TiendanubeSyncGuard). El envío va con afterResponse() para no frenar el
 * guardado ni depender de un worker de colas.
 */
class TiendanubeAutoSync
{
    public static function queue(Model $model): void
    {
        if (TiendanubeSyncGuard::muted()) {
            return;
        }

        try {
            if (! app(TiendanubeClient::class)->isConfigured()) {
                return;
            }
        } catch (\Throwable $e) {
            // Sin fila de configuración (o cualquier error al leerla): no
            // sincronizamos. Nunca debe romper un guardado local.
            return;
        }

        PushToTiendanubeJob::dispatch($model)->afterResponse();
    }
}
