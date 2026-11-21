<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\TiendaPedido;
use App\Support\StockAdjuster;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Libera las reservas de stock de pedidos web que vencieron sin pagarse
 * (ver Checkout::confirmar y CompanySettings::tienda_online_reserva_minutos).
 * El Remito creado al reservar no tiene número fiscal ni valor legal (ver
 * TipoComprobanteInterno::RemitoX), así que es seguro borrarlo entero.
 */
class ReleaseExpiredTiendaPedidos extends Command
{
    protected $signature = 'tienda:release-expired-pedidos';

    protected $description = 'Devuelve el stock reservado por pedidos de la tienda online que vencieron sin pagarse.';

    public function handle(): int
    {
        // Solo IDs acá: el `withoutOverlapping()` del scheduler (routes/
        // console.php) ya evita que dos corridas de ESTE comando se pisen,
        // pero no cubre un `php artisan tienda:release-expired-pedidos`
        // manual corriendo en paralelo, ni una carrera contra el pago real
        // del pedido (Checkout/MercadoPago) en el mismo instante. Cada
        // pedido se vuelve a leer y lockear individualmente más abajo, ya
        // adentro de su propia transacción, en vez de confiar en los datos
        // traídos por esta consulta inicial.
        $ids = TiendaPedido::where('status', 'pending_payment')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->pluck('id');

        $liberados = 0;

        foreach ($ids as $id) {
            $seLiberó = Cache::lock("tienda-pedido:release:{$id}", 10)->block(5, function () use ($id) {
                return DB::transaction(function () use ($id) {
                    // Releído y lockeado fresco adentro de la transacción:
                    // si mientras esperábamos el lock el cliente terminó de
                    // pagar (o esta misma corrida quedó pisada por otra en
                    // paralelo), el estado real ya no es 'pending_payment' y
                    // no hay que tocar nada.
                    $pedido = TiendaPedido::with('invoice.items')
                        ->whereKey($id)
                        ->lockForUpdate()
                        ->first();

                    if (! $pedido || $pedido->status !== 'pending_payment') {
                        return false;
                    }

                    $invoice = $pedido->invoice;

                    if ($invoice && $invoice->status !== InvoiceStatus::Paid->value) {
                        $items = $invoice->items->map(fn ($item) => [
                            'product_id' => $item->product_id,
                            'quantity' => $item->quantity,
                        ])->all();

                        StockAdjuster::apply($items, 1, $pedido->sucursal_id);

                        $invoice->items()->delete();
                        $invoice->delete();
                    }

                    $pedido->update(['status' => 'expired', 'invoice_id' => null]);

                    return true;
                });
            });

            if ($seLiberó) {
                $liberados++;
            }
        }

        if ($liberados > 0) {
            $this->info("Liberados {$liberados} pedido(s) vencido(s).");
        }

        return self::SUCCESS;
    }
}
