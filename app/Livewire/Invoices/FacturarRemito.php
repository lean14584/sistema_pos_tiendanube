<?php

namespace App\Livewire\Invoices;

use App\Enums\AlicuotaIva;
use App\Enums\TipoComprobanteInterno;
use App\Models\CompanySettings;
use App\Models\Invoice;
use App\Support\InvoiceNumberGenerator;
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

        if ($this->remito->facturaGenerada() !== null) {
            $this->addError('tipo_comprobante_interno', 'Este remito ya fue facturado.');

            return;
        }

        $factura = InvoiceNumberGenerator::withLock($tipo->value, fn () => DB::transaction(function () use ($tipo) {
            $factura = Invoice::create([
                'number' => InvoiceNumberGenerator::next($tipo->value),
                'client_id' => $this->remito->client_id,
                'tipo_comprobante_interno' => $tipo,
                'remito_id' => $this->remito->id,
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(15)->toDateString(),
                'tax_rate' => 0,
                // El stock ya lo movió el remito: la factura NO lo vuelve a tocar.
                'afecta_stock' => false,
                'status' => 'draft',
                'notes' => 'Facturación del remito '.$this->remito->number,
            ]);

            foreach ($this->remito->items as $item) {
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
        }));

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
