<?php

namespace App\Livewire\Quotes;

use App\Enums\QuoteStatus;
use App\Enums\TipoComprobanteInterno;
use App\Models\CompanySettings;
use App\Models\Invoice;
use App\Models\PuntoVenta;
use App\Models\Quote;
use App\Support\CurrentSucursal;
use App\Support\InvoiceNumberGenerator;
use App\Support\StockAdjuster;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Quote $quote;

    public string $priceMode = 'keep';

    /** Vacío = usar el único/por defecto de la sucursal activa (no se muestra selector). */
    public string $punto_venta = '';

    public function mount(Quote $quote): void
    {
        $this->quote = $quote;

        $default = CurrentSucursal::get()?->puntoVentaPorDefecto();
        $this->punto_venta = $default ? (string) $default->numero : '';
    }

    /** Puntos de venta activos de la sucursal donde se está convirtiendo el presupuesto. */
    #[Computed]
    public function puntosVentaOpciones()
    {
        $sucursalId = CurrentSucursal::id();

        return $sucursalId
            ? PuntoVenta::where('sucursal_id', $sucursalId)->where('active', true)->orderBy('id')->get()
            : collect();
    }

    public function setStatus(string $status): void
    {
        $this->quote->update(['status' => $status]);
    }

    public function delete(): void
    {
        abort_unless(Auth::user()->puedeEliminar(), 403, 'Tu rol no tiene permiso para eliminar presupuestos.');

        $this->quote->delete();

        session()->flash('status', 'Presupuesto eliminado.');
        $this->redirect(route('quotes.index'), navigate: true);
    }

    public function convertToInvoice(): void
    {
        $updatePrices = $this->priceMode === 'update';

        // Mismo criterio que FacturarRemito: la fiscal habilitada preferida,
        // o Factura B si no hay ninguna (nunca Remito/Devolución acá).
        $default = CompanySettings::current()->tipoComprobantePorDefecto();
        $tipo = $default->esFiscal() ? $default : TipoComprobanteInterno::FacturaB;

        $puntoVentaNumero = $this->puntosVentaOpciones()->firstWhere('numero', (int) $this->punto_venta)?->numero;

        if ($puntoVentaNumero === null) {
            $this->addError('punto_venta', 'Elegí un punto de venta válido.');

            return;
        }

        // MEJORA: antes el chequeo "¿ya se convirtió?" no tenía lock ni
        // releía de la base, así que dos submits casi simultáneos (doble
        // clic, dos pestañas) pasaban el chequeo los dos y generaban dos
        // facturas del mismo presupuesto, duplicando venta y stock. Mismo
        // mecanismo que ya usan NotasCredito\Create y FacturarRemito: todo
        // el check-then-act adentro del lock, releyendo con fresh().
        try {
            $invoice = Cache::lock("quote:convert:{$this->quote->id}", 10)->block(5, function () use ($updatePrices, $tipo, $puntoVentaNumero) {
                $quote = $this->quote->fresh();

                if ($quote->status === QuoteStatus::Converted) {
                    throw new \RuntimeException('Este presupuesto ya fue convertido a factura.');
                }

                return InvoiceNumberGenerator::withLock($tipo->value, fn () => DB::transaction(function () use ($updatePrices, $tipo, $puntoVentaNumero, $quote) {
                    $invoice = Invoice::create([
                        'number' => InvoiceNumberGenerator::next($tipo->value, null, $puntoVentaNumero),
                        'client_id' => $quote->client_id,
                        'punto_venta' => $puntoVentaNumero,
                        'tipo_comprobante_interno' => $tipo,
                        'issue_date' => now()->toDateString(),
                        'due_date' => now()->addDays(15)->toDateString(),
                        'tax_rate' => $quote->tax_rate,
                        'notes' => $quote->notes,
                        'status' => 'draft',
                    ]);

                    $stockItems = [];

                    foreach ($quote->items as $item) {
                        $product = $item->product_id ? $item->product : null;

                        $data = ($updatePrices && $product)
                            ? [
                                'product_id' => $product->id,
                                'description' => $product->name,
                                'quantity' => $item->quantity,
                                'unit_price' => $product->price,
                            ]
                            : [
                                'product_id' => $item->product_id,
                                'description' => $item->description,
                                'quantity' => $item->quantity,
                                'unit_price' => $item->unit_price,
                            ];

                        $invoice->items()->create($data);
                        $stockItems[] = $data;
                    }

                    StockAdjuster::apply($stockItems, $tipo->stockSign());

                    $quote->update([
                        'status' => QuoteStatus::Converted,
                        'converted_invoice_id' => $invoice->id,
                    ]);

                    return $invoice;
                }), null, $puntoVentaNumero);
            });
        } catch (\RuntimeException $e) {
            $this->addError('priceMode', $e->getMessage());

            return;
        }

        $this->redirect(route('invoices.show', $invoice), navigate: true);
    }

    public function render()
    {
        $this->quote->load('client', 'items.product');

        return view('livewire.quotes.show', [
            'editableStatuses' => QuoteStatus::editable(),
        ]);
    }
}
