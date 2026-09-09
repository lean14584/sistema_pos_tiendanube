<?php

namespace App\Livewire\Invoices;

use App\Enums\AlicuotaIva;
use App\Enums\TipoComprobanteInterno;
use App\Models\CompanySettings;
use App\Models\Invoice;
use App\Support\CurrentSucursal;
use App\Support\InvoiceNumberGenerator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class FacturarRemito extends Component
{
    public Invoice $remito;

    public string $tipo_comprobante_interno = 'factura_b';

    public function mount(Invoice $invoice): void
    {
        abort_unless(
            Auth::user()?->esAdminGlobal() || $invoice->sucursal_id === CurrentSucursal::id(),
            403,
            'No podés facturar un remito de otra sucursal.'
        );

        abort_if(! $invoice->esRemito(), 403, 'Solo se puede facturar un Remito.');
        abort_if($invoice->facturaGenerada() !== null, 403, 'Este remito ya fue facturado.');

        $this->remito = $invoice;

        // Tipo destino por defecto: la factura fiscal habilitada preferida.
        $default = CompanySettings::current()->tipoComprobantePorDefecto();
        $this->tipo_comprobante_interno = $default->esFiscal()
            ? $default->value
            : TipoComprobanteInterno::FacturaB->value;
    }

    /** Tipos a los que se puede facturar: solo Factura A/B habilitadas. */
    private function tiposDestino(): array
    {
        return array_values(array_filter(
            CompanySettings::current()->tiposComprobanteSeleccionables(),
            fn (TipoComprobanteInterno $t) => $t->esFiscal() && ! $t->esNotaCredito(),
        ));
    }

    public function total(): float
    {
        return (float) $this->remito->total;
    }

    public function save(): void
    {
        $tipo = TipoComprobanteInterno::tryFrom($this->tipo_comprobante_interno);

        if (! $tipo || ! in_array($tipo, $this->tiposDestino(), true)) {
            $this->addError('tipo_comprobante_interno', 'Elegí un tipo de factura válido y habilitado.');

            return;
        }

        // El chequeo "¿ya se facturó este remito?" y la creación de la
        // factura tienen que ser una sola unidad atómica: sin este lock, dos
        // submits casi simultáneos (doble clic) pueden pasar el chequeo los
        // dos y generar dos facturas del mismo remito (no hay constraint
        // único en remito_id). Mismo mecanismo que ya usa NotasCredito\Create.
        try {
            $factura = Cache::lock("facturar-remito:{$this->remito->id}", 10)->block(5, function () use ($tipo) {
                $remito = $this->remito->fresh();

                if ($remito->facturaGenerada() !== null) {
                    throw new \RuntimeException('Este remito ya fue facturado.');
                }

                // Punto de venta de la factura resultante = el del remito
                // original (misma sucursal, misma venta física), no la
                // sesión de quien la factura ahora. Fallback al por defecto
                // de la sucursal solo para remitos viejos creados antes de
                // que existiera esta columna.
                $puntoVentaNumero = $remito->punto_venta
                    ?? $remito->sucursal?->puntoVentaPorDefecto()?->numero
                    ?? 1;

                return InvoiceNumberGenerator::withLock($tipo->value, fn () => DB::transaction(function () use ($tipo, $remito, $puntoVentaNumero) {
                    $factura = Invoice::create([
                        'number' => InvoiceNumberGenerator::next($tipo->value, null, $puntoVentaNumero),
                        'client_id' => $remito->client_id,
                        'sucursal_id' => $remito->sucursal_id,
                        'punto_venta' => $puntoVentaNumero,
                        'tipo_comprobante_interno' => $tipo,
                        'remito_id' => $remito->id,
                        'issue_date' => now()->toDateString(),
                        'due_date' => now()->addDays(15)->toDateString(),
                        'tax_rate' => 0,
                        // El stock ya lo movió el remito: la factura NO lo vuelve a tocar.
                        'afecta_stock' => false,
                        'status' => 'draft',
                        'notes' => 'Facturación del remito '.$remito->number,
                    ]);

                    foreach ($remito->items as $item) {
                        $factura->items()->create([
                            'product_id' => $item->product_id,
                            'description' => $item->description,
                            'quantity' => $item->quantity,
                            'unit_price' => $item->unit_price,
                            'discount_percent' => $item->discount_percent,
                            'iva_rate' => AlicuotaIva::normalizar($item->iva_rate_efectiva),
                        ]);
                    }

                    return $factura;
                }), null, $puntoVentaNumero);
            });
        } catch (\RuntimeException $e) {
            $this->addError('tipo_comprobante_interno', $e->getMessage());

            return;
        }

        session()->flash('status', 'Factura generada a partir del remito.');
        $this->redirect(route('invoices.show', $factura), navigate: true);
    }

    public function render()
    {
        return view('livewire.invoices.facturar-remito', [
            'tiposDestino' => $this->tiposDestino(),
        ]);
    }
}
