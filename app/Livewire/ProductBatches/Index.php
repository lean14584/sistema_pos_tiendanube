<?php

namespace App\Livewire\ProductBatches;

use App\Enums\StockAdjustmentReason;
use App\Livewire\Concerns\ScopedToSucursal;
use App\Livewire\Concerns\ShowsToasts;
use App\Models\ProductBatch;
use App\Models\ProductStock;
use App\Models\StockAdjustment;
use App\Models\Sucursal;
use App\Support\CurrentSucursal;
use App\Support\StockAdjuster;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Lotes de productos perecederos con vencimiento (ver ProductBatch). Se
 * cargan al recibir mercadería (Purchases\Create); acá solo se listan y se
 * dan de baja cuando vencen. No confundir con el módulo "Vencimientos"
 * (finanzas: cuentas por cobrar/pagar) — nombre distinto a propósito.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use ScopedToSucursal;
    use ShowsToasts;
    use WithPagination;

    #[Url]
    public string $sucursal_id = '';

    /** '' = todos, 'vencido', 'por_vencer', 'ok' */
    #[Url]
    public string $estado = '';

    public ?int $bajaBatchId = null;

    public string $bajaCantidad = '';

    public string $bajaNotes = '';

    public function abrirBaja(int $batchId): void
    {
        $batch = ProductBatch::findOrFail($batchId);
        $this->bajaBatchId = $batch->id;
        $this->bajaCantidad = (string) $batch->quantity_remaining;
        $this->bajaNotes = '';
    }

    public function cancelarBaja(): void
    {
        $this->reset(['bajaBatchId', 'bajaCantidad', 'bajaNotes']);
    }

    public function confirmarBaja(): void
    {
        $batch = ProductBatch::find($this->bajaBatchId);

        if (! $batch) {
            $this->cancelarBaja();

            return;
        }

        // Un cajero/vendedor solo da de baja lotes de SU sucursal, sin
        // importar qué id de lote le llegue desde el cliente (mismo criterio
        // que StockTransfers\Index con el origen del envío).
        if (! $this->puedeVerTodasLasSucursales() && $batch->sucursal_id !== CurrentSucursal::id()) {
            $this->cancelarBaja();
            $this->toastError('No podés dar de baja un lote de otra sucursal.');

            return;
        }

        $this->validate([
            'bajaCantidad' => ['required', 'numeric', 'min:0.01', 'max:'.(float) $batch->quantity_remaining],
        ]);

        DB::transaction(function () use ($batch) {
            // Releemos el lote CON LOCK adentro de la transacción: el
            // chequeo de arriba (fuera de la transacción, contra
            // $batch->quantity_remaining en memoria) es solo feedback rápido
            // — sin este segundo chequeo, un doble clic puede disparar dos
            // requests que resten cada uno sobre el mismo valor viejo de
            // quantity_remaining, dejando el lote con un remanente que no
            // coincide con lo que realmente se dio de baja (el stock total,
            // vía increment atómico en StockAdjuster, sí queda bien igual).
            $freshBatch = ProductBatch::where('id', $batch->id)->lockForUpdate()->first();

            if (! $freshBatch || $freshBatch->quantity_remaining <= 0) {
                return; // Ya se terminó de dar de baja en otro request.
            }

            // El stock se maneja en unidades enteras (ver Product::$stock):
            // una baja parcial de un lote pesado se redondea a la unidad más
            // cercana, igual que ya hace Ajustes de Stock. Se topea a lo que
            // efectivamente queda del lote (releído arriba), no a lo tipeado.
            $cantidad = (int) round(min((float) $this->bajaCantidad, (float) $freshBatch->quantity_remaining));

            if ($cantidad <= 0) {
                return;
            }

            $sucursalId = $freshBatch->sucursal_id;

            $row = ProductStock::where('product_id', $freshBatch->product_id)->where('sucursal_id', $sucursalId)->lockForUpdate()->first();
            $previous = $row?->stock ?? 0;

            StockAdjuster::applyManualDelta($freshBatch->product_id, -$cantidad, $sucursalId);

            StockAdjustment::create([
                'product_id' => $freshBatch->product_id,
                'sucursal_id' => $sucursalId,
                'user_id' => auth()->id(),
                'previous_stock' => $previous,
                'new_stock' => $previous - $cantidad,
                'reason' => StockAdjustmentReason::Vencimiento->value,
                'notes' => $this->bajaNotes ?: ($freshBatch->batch_number ? "Baja de lote {$freshBatch->batch_number}" : 'Baja de lote vencido'),
            ]);

            $freshBatch->quantity_remaining = max(0, (float) $freshBatch->quantity_remaining - (float) $this->bajaCantidad);
            if ($freshBatch->quantity_remaining <= 0) {
                $freshBatch->written_off_at = now();
            }
            $freshBatch->save();
        });

        $this->toastSuccess('Lote dado de baja.');
        $this->cancelarBaja();
    }

    public function render()
    {
        $sucursalId = $this->puedeVerTodasLasSucursales()
            ? ($this->sucursal_id !== '' ? (int) $this->sucursal_id : null)
            : CurrentSucursal::id();

        $today = now()->toDateString();

        $batches = ProductBatch::with(['product', 'sucursal'])
            ->active()
            ->when($sucursalId !== null, fn ($q) => $q->where('sucursal_id', $sucursalId))
            // expiration_date ya es una columna DATE pura (sin hora): usar
            // whereDate() envuelve la columna en DATE(...), lo que impide
            // usar el índice (sucursal_id, expiration_date) ya existente —
            // un where() directo contra el mismo string 'Y-m-d' es
            // equivalente y sargable. Mismo criterio que SalesReport/
            // LibroIvaCalculator, que ya evitan whereDate() a propósito.
            ->when($this->estado === 'vencido', fn ($q) => $q->where('expiration_date', '<', $today))
            ->when($this->estado === 'por_vencer', fn ($q) => $q->where('expiration_date', '>=', $today)->expiringWithin(ProductBatch::DIAS_ALERTA))
            ->when($this->estado === 'ok', fn ($q) => $q->where('expiration_date', '>', now()->addDays(ProductBatch::DIAS_ALERTA)->toDateString()))
            ->orderBy('expiration_date')
            ->paginate(20);

        return view('livewire.product-batches.index', [
            'batches' => $batches,
            'sucursales' => $this->puedeVerTodasLasSucursales() ? Sucursal::forSelectCached() : collect(),
            'puedeVerTodasLasSucursales' => $this->puedeVerTodasLasSucursales(),
        ]);
    }
}
