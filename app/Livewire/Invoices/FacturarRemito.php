<?php

namespace App\Livewire\Invoices;

use App\Enums\TipoComprobanteInterno;
use App\Models\CompanySettings;
use App\Models\Invoice;
use App\Support\CurrentSucursal;
use App\Support\RemitoFacturador;
use Illuminate\Support\Facades\Auth;
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

        try {
            $factura = RemitoFacturador::facturar($this->remito, $tipo);
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
