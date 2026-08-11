<?php

namespace App\Jobs;

use App\Models\Category;
use App\Models\Client;
use App\Models\Product;
use App\Services\Tiendanube\TiendanubeClient;
use App\Services\Tiendanube\TiendanubeSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Empuja a Tiendanube un registro que cambió en el sistema (producto, cliente
 * o categoría). Lo dispara el observer correspondiente con ->afterResponse(),
 * así el guardado no espera a la API y no hace falta un worker aparte.
 */
class PushToTiendanubeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Model $model) {}

    public function handle(TiendanubeSync $sync, TiendanubeClient $client): void
    {
        if (! $client->isConfigured()) {
            return;
        }

        try {
            match (true) {
                $this->model instanceof Product => $sync->pushProduct($this->model),
                $this->model instanceof Client => $sync->pushClient($this->model),
                $this->model instanceof Category => $sync->pushCategory($this->model),
                default => null,
            };
        } catch (\Throwable $e) {
            // Nunca romper por un fallo de sincronización: queda en el log.
            report($e);
        }
    }
}
