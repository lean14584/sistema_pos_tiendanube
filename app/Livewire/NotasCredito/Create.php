<?php

namespace App\Livewire\NotasCredito;

use App\Enums\AlicuotaIva;
use App\Enums\PaymentMethod;
use App\Enums\TipoComprobante;
use App\Enums\TipoComprobanteInterno;
use App\Models\Invoice;
use App\Support\CashLinker;
use App\Support\InvoiceNumberGenerator;
use App\Support\StockAdjuster;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;

#[Layout('layouts.app')]
class Create extends Component
{
    public Invoice $invoice;

    public bool $afecta_stock = true;

    /** @var array<int, array{product_id: ?int, description: string, quantity: string, unit_price: string}> */
    public array $items = [];

    /** @var array<int, array{method: string, amount: string}> */
    public array $payments = [];

    public function mount(Invoice $invoice): void
    {
        abort_if(! $invoice->isFiscal, 403, 'Solo se puede emitir una Nota de Crédito para una factura con CAE.');
        abort_if($invoice->related_invoice_id !== null, 403, 'Una Nota de Crédito no puede tener, a su vez, otra Nota de Crédito.');

        $this->invoice = $invoice;

        $this->items = $invoice->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'description' => $item->description,
            'quantity' => (string) $item->quantity,
            'unit_price' => (string) $item->unit_price,
            'iva_rate' => AlicuotaIva::normalizar($item->iva_rate_efectiva),
        ])->all();
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function subtotal(): float
    {
        return collect($this->items)->sum(fn ($item) => (float) $item['quantity'] * (float) $item['unit_price']);
    }

    public function netoGravado(): float
    {
        return collect($this->items)
            ->filter(fn ($item) => (float) ($item['iva_rate'] ?? 0) > 0)
            ->sum(fn ($item) => (float) $item['quantity'] * (float) $item['unit_price']);
    }

    public function netoExento(): float
    {
        return collect($this->items)
            ->filter(fn ($item) => (float) ($item['iva_rate'] ?? 0) <= 0)
            ->sum(fn ($item) => (float) $item['quantity'] * (float) $item['unit_price']);
    }

    public function taxAmount(): float
    {
        return collect($this->items)->sum(
            fn ($item) => (float) $item['quantity'] * (float) $item['unit_price'] * ((float) ($item['iva_rate'] ?? 0) / 100)
        );
    }

    public function total(): float
    {
        return $this->subtotal() + $this->taxAmount();
    }

    /**
     * @return array<int, array{tasa: float, iva: float}>
     */
    public function ivaBreakdown(): array
    {
        return collect($this->items)
            ->filter(fn ($item) => (float) ($item['iva_rate'] ?? 0) > 0)
            ->groupBy(fn ($item) => (string) (float) $item['iva_rate'])
            ->map(fn ($grupo, $tasa) => [
                'tasa' => (float) $tasa,
                'iva' => $grupo->sum(fn ($item) => (float) $item['quantity'] * (float) $item['unit_price'] * ((float) $item['iva_rate'] / 100)),
            ])
            ->sortBy('tasa')
            ->values()
            ->all();
    }

    public function paidTotal(): float
    {
        return collect($this->payments)->sum(fn ($p) => (float) $p['amount']);
    }

    public function remaining(): float
    {
        return max(0, round($this->total() - $this->paidTotal(), 2));
    }

    public function addPayment(): void
    {
        $this->payments[] = [
            'method' => 'efectivo',
            'amount' => (string) $this->remaining(),
        ];
    }

    public function removePayment(int $index): void
    {
        unset($this->payments[$index]);
        $this->payments = array_values($this->payments);
    }

    public function save(): void
    {
        $validItems = collect($this->items)->filter(fn ($item) => trim($item['description']) !== '');

        if ($validItems->isEmpty()) {
            $this->addError('items', 'Agregá al menos un ítem con descripción.');

            return;
        }

        $cantidadOPrecioInvalido = $validItems->contains(
            fn ($item) => (float) $item['quantity'] <= 0 || (float) $item['unit_price'] < 0
        );

        if ($cantidadOPrecioInvalido) {
            $this->addError('items', 'Cada ítem necesita una cantidad mayor a cero y un precio unitario válido.');

            return;
        }

        $tipoNC = $this->invoice->tipo_comprobante === TipoComprobante::FacturaA
            ? TipoComprobanteInterno::NotaCreditoA
            : TipoComprobanteInterno::NotaCreditoB;

        // El chequeo de "cuánto queda por acreditar" y la creación de la NC
        // (que mueve stock y caja ya en borrador, antes de emitir a AFIP)
        // tienen que ser una sola unidad atómica por factura: si no,
        // dos submits casi simultáneos (doble clic, o dos personas) contra
        // la misma factura pueden pasar el chequeo los dos y duplicar la
        // reversión de stock/plata. Se relee la factura ya adentro del lock.
        try {
            $notaCredito = Cache::lock("nota-credito:factura:{$this->invoice->id}", 10)->block(5, function () use ($validItems, $tipoNC) {
                $invoice = $this->invoice->fresh();

                $disponible = round((float) $invoice->total - (float) $invoice->creditedTotal, 2);

                if (round($this->total(), 2) > $disponible + 0.009) {
                    throw new RuntimeException(
                        'El total a acreditar ($'.money($this->total())
                            .') supera el saldo pendiente de acreditar de la factura ($'.money(max($disponible, 0)).').'
                    );
                }

                // Punto de venta, stock y caja de la NC son los de la
                // sucursal de la FACTURA ORIGINAL, no la sesión activa de
                // quien procesa la devolución ahora (puede ser un admin
                // parado en otra sucursal, o haber pasado tiempo).
                return InvoiceNumberGenerator::withLock($tipoNC->value, fn () => DB::transaction(function () use ($validItems, $tipoNC, $invoice) {
                    $nota = Invoice::create([
                        'number' => InvoiceNumberGenerator::next($tipoNC->value, $invoice->sucursal_id),
                        'client_id' => $invoice->client_id,
                        'sucursal_id' => $invoice->sucursal_id,
                        'related_invoice_id' => $invoice->id,
                        'tipo_comprobante_interno' => $tipoNC,
                        'afecta_stock' => $this->afecta_stock,
                        'issue_date' => now()->toDateString(),
                        'due_date' => now()->toDateString(),
                        'tax_rate' => 0,
                        'status' => 'draft',
                    ]);

                    foreach ($validItems as $item) {
                        $nota->items()->create([
                            'product_id' => $item['product_id'],
                            'description' => $item['description'],
                            'quantity' => $item['quantity'],
                            'unit_price' => $item['unit_price'],
                            'iva_rate' => $item['iva_rate'] ?? '21',
                        ]);
                    }

                    StockAdjuster::apply($validItems, $this->afecta_stock ? $tipoNC->stockSign() : 0, $invoice->sucursal_id);

                    foreach ($this->payments as $payment) {
                        if ((float) $payment['amount'] > 0) {
                            $created = $nota->payments()->create($payment);
                            CashLinker::linkInvoiceRefund($nota, $created, $invoice->sucursal_id);
                        }
                    }

                    return $nota;
                }), $invoice->sucursal_id);
            });
        } catch (RuntimeException $e) {
            $this->addError('items', $e->getMessage());

            return;
        }

        session()->flash('status', 'Nota de crédito generada.');
        $this->redirect(route('invoices.show', $notaCredito), navigate: true);
    }

    public function render()
    {
        return view('livewire.notas-credito.create', [
            'paymentMethods' => PaymentMethod::cases(),
        ]);
    }
}
