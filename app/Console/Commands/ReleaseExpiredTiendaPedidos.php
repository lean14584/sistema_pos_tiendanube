<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\TiendaPedido;
use App\Support\StockAdjuster;
use Illuminate\Console\Command;
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
        $expirados = TiendaPedido::where('status', 'pending_payment')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->with('invoice.items')
            ->get();

        foreach ($expirados as $pedido) {
            DB::transaction(function () use ($pedido) {
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
            });
        }

        if ($expirados->isNotEmpty()) {
            $this->info("Liberados {$expirados->count()} pedido(s) vencido(s).");
        }

        return self::SUCCESS;
    }
}
