<?php

namespace App\Livewire\Pos;

use App\Enums\AlicuotaIva;
use App\Enums\PaymentMethod;
use App\Models\Client;
use App\Models\CompanySettings;
use App\Models\Invoice;
use App\Services\TicketPrinterService;
use App\Support\CashLinker;
use App\Support\StockAdjuster;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Punto de venta rápido: grilla táctil de productos + lector de código de
 * barras, carrito y cobro en pocos toques. Pensado para el mostrador.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    /** @var array<int, array{product_id:int, description:string, sku:?string, unit_price:float, iva_rate:string, quantity:int}> */
    public array $cart = [];

    public string $search = '';

    public string $barcode = '';

    public string $paymentMethod = 'efectivo';

    public bool $printOnSale = true;

    public function mount(): void
    {
        $this->paymentMethod = PaymentMethod::cases()[0]->value;
    }

    #[Computed]
    public function productos()
    {
        return \App\Models\Product::query()
            ->when(trim($this->search) !== '', function ($q) {
                $term = trim($this->search);
                $q->where('name', 'like', "%{$term}%")->orWhere('sku', 'like', "%{$term}%");
            })
            ->orderBy('name')
            ->limit(60)
            ->get();
    }

    public function addProduct(int $productId): void
    {
        $product = \App\Models\Product::find($productId);

        if (! $product) {
            return;
        }

        foreach ($this->cart as $i => $item) {
            if ($item['product_id'] === $product->id) {
                $this->cart[$i]['quantity']++;

                return;
            }
        }

        $this->cart[] = [
            'product_id' => $product->id,
            'description' => $product->name,
            'sku' => $product->sku,
            'unit_price' => (float) $product->price,
            'iva_rate' => AlicuotaIva::normalizar($product->iva_rate),
            'quantity' => 1,
        ];
    }

    /**
     * Lo dispara el lector de código de barras (o Enter en el buscador): busca
     * por SKU exacto, o por nombre si es único.
     */
    public function addByBarcode(): void
    {
        $code = trim($this->barcode);
        $this->barcode = '';

        if ($code === '') {
            return;
        }

        $product = \App\Models\Product::where('sku', $code)->first()
            ?? \App\Models\Product::where('name', $code)->first();

        if (! $product) {
            $this->addError('barcode', "No se encontró un producto con código «{$code}».");

            return;
        }

        $this->resetErrorBag('barcode');
        $this->addProduct($product->id);
    }

    public function inc(int $index): void
    {
        if (isset($this->cart[$index])) {
            $this->cart[$index]['quantity']++;
        }
    }

    public function dec(int $index): void
    {
        if (! isset($this->cart[$index])) {
            return;
        }

        if ($this->cart[$index]['quantity'] <= 1) {
            $this->removeItem($index);

            return;
        }

        $this->cart[$index]['quantity']--;
    }

    public function removeItem(int $index): void
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
    }

    public function vaciar(): void
    {
        $this->cart = [];
    }

    public function total(): float
    {
        return collect($this->cart)->sum(
            fn ($i) => $i['unit_price'] * $i['quantity'] * (1 + (float) $i['iva_rate'] / 100)
        );
    }

    public function itemsCount(): int
    {
        return collect($this->cart)->sum('quantity');
    }

    public function cobrar(): void
    {
        if ($this->cart === []) {
            $this->addError('cart', 'Agregá al menos un producto.');

            return;
        }

        $tipo = CompanySettings::current()->tipoComprobantePorDefecto();

        $invoice = DB::transaction(function () use ($tipo) {
            $invoice = Invoice::create([
                'number' => $this->nextNumber($tipo->value),
                'client_id' => Client::consumidorFinal()->id,
                'tipo_comprobante_interno' => $tipo,
                'issue_date' => now()->toDateString(),
                'due_date' => now()->toDateString(),
                'tax_rate' => 0,
                'status' => 'paid',
            ]);

            foreach ($this->cart as $item) {
                $invoice->items()->create([
                    'product_id' => $item['product_id'],
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'iva_rate' => $item['iva_rate'],
                ]);
            }

            StockAdjuster::apply($this->cart, $tipo->stockSign());

            $payment = $invoice->payments()->create([
                'method' => $this->paymentMethod,
                'amount' => round($this->total(), 2),
            ]);
            CashLinker::linkInvoicePayment($invoice, $payment);

            return $invoice;
        });

        if ($this->printOnSale) {
            try {
                app(TicketPrinterService::class)->imprimir($invoice);
            } catch (\Throwable $e) {
                session()->flash('error', 'La venta se guardó, pero no se pudo imprimir el ticket: '.$e->getMessage());
            }
        }

        $this->cart = [];
        $this->search = '';
        session()->flash('status', "Venta {$invoice->number} cobrada por $".number_format((float) $invoice->total, 2).'.');
    }

    private function nextNumber(string $tipo): string
    {
        $prefix = match ($tipo) {
            'remito_x' => 'REM',
            'devolucion' => 'DEV',
            default => 'FAC',
        };

        $count = Invoice::where('number', 'like', "{$prefix}-%")->count() + 1;

        return "{$prefix}-".str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    public function render()
    {
        return view('livewire.pos.index', [
            'paymentMethods' => PaymentMethod::cases(),
        ]);
    }
}
