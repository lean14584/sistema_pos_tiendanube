<?php

namespace App\Livewire\Pos;

use App\Enums\AlicuotaIva;
use App\Enums\PaymentMethod;
use App\Enums\TipoComprobanteInterno;
use App\Models\Client;
use App\Models\CompanySettings;
use App\Models\Invoice;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionGroup;
use App\Services\TicketPrinterService;
use App\Support\CashLinker;
use App\Support\InvoiceNumberGenerator;
use App\Support\PromotionEngine;
use App\Support\StockAdjuster;
use Illuminate\Support\Collection;
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
    /** @var array<int, array{product_id:int, description:string, sku:?string, unit_price:float, discount:float, iva_rate:string, quantity:int}> */
    public array $cart = [];

    public string $barcode = '';

    public string $clientQuery = '';

    /** Cliente al que se factura. Por defecto, Consumidor Final. */
    public ?int $client_id = null;

    /** Lista de precios aplicada. Por defecto, la del cliente o la predeterminada. */
    public ?int $price_list_id = null;

    /** @var array<int, array{method: string, amount: string}> */
    public array $payments = [];

    public bool $printOnSale = true;

    /** Tipo de comprobante a generar (Factura A/B, Remito X, etc.). */
    public string $tipo_comprobante_interno = '';

    public function mount(): void
    {
        $cf = Client::consumidorFinal();
        $this->client_id = $cf->id;
        $this->price_list_id = $cf->price_list_id; // null = precio base
        $this->tipo_comprobante_interno = CompanySettings::current()->tipoComprobantePorDefecto()->value;
    }

    /** Lista de precios vigente. null = precio base (sin ajuste). */
    public function currentPriceList(): ?PriceList
    {
        return $this->price_list_id ? PriceList::find($this->price_list_id) : null;
    }

    /** Al cambiar de cliente, tomo su lista asignada (o precio base) y recalculo. */
    public function updatedClientId($value): void
    {
        $client = Client::find($value);
        $this->price_list_id = $client?->price_list_id;
        $this->repriceCart();
    }

    public function updatedPriceListId(): void
    {
        $this->repriceCart();
    }

    #[Computed]
    public function clientResults()
    {
        $term = trim($this->clientQuery);

        if ($term === '') {
            return collect();
        }

        return Client::where('name', 'like', "%{$term}%")
            ->orWhere('phone', 'like', "%{$term}%")
            ->limit(8)
            ->get();
    }

    public function selectClient(int $clientId): void
    {
        $this->clientQuery = '';
        $this->client_id = $clientId;
        $this->updatedClientId($clientId);
    }

    /** Reaplica el precio de la lista vigente a cada ítem del carrito. */
    private function repriceCart(): void
    {
        $list = $this->currentPriceList();
        $productIds = collect($this->cart)->pluck('product_id')->unique()->all();
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        foreach ($this->cart as $i => $item) {
            if ($product = $products->get($item['product_id'])) {
                $this->cart[$i]['unit_price'] = $product->priceForList($list);
            }
        }
    }

    /**
     * Sugerencias en vivo mientras se tipea en el lector: el mismo input
     * escanea (código exacto + Enter, vía addByBarcode) y busca por nombre o
     * SKU parcial para elegir con el mouse/touch sin tener que saber el
     * código exacto de memoria.
     */
    #[Computed]
    public function barcodeResults()
    {
        $term = trim($this->barcode);

        if ($term === '') {
            return collect();
        }

        return Product::where('name', 'like', "%{$term}%")
            ->orWhere('sku', 'like', "%{$term}%")
            ->limit(8)
            ->get();
    }

    public function selectFromBarcode(int $productId): void
    {
        $this->barcode = '';
        $this->resetErrorBag('barcode');
        $this->addProduct($productId);
    }

    public function addProduct(int $productId): void
    {
        $product = Product::find($productId);

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
            'unit_price' => $product->priceForList($this->currentPriceList()),
            'discount' => 0,
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

        $product = Product::where('sku', $code)->first()
            ?? Product::where('name', $code)->first();

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

    /**
     * Promociones vigentes de los productos que están en el carrito,
     * indexadas por product_id (a lo sumo una por producto).
     *
     * @return Collection<int, Promotion>
     */
    #[Computed]
    public function promoMap()
    {
        $ids = collect($this->cart)->pluck('product_id')->unique()->all();

        if ($ids === []) {
            return collect();
        }

        return Promotion::activeNow()->whereIn('product_id', $ids)->get()->keyBy('product_id');
    }

    /**
     * Descuento por familia (promo NxM entre varios productos) asignado a
     * cada producto del carrito. Un producto con promo propia NO entra a la
     * familia. Devuelve product_id => ['amount' => float, 'label' => string].
     *
     * @return Collection<int, array{amount: float, label: string}>
     */
    #[Computed]
    public function groupAllocations()
    {
        $cartByProduct = collect($this->cart)->keyBy('product_id');

        if ($cartByProduct->isEmpty()) {
            return collect();
        }

        $conPromoPropia = $this->promoMap->keys()->all();
        $groups = PromotionGroup::activeNow()->with('products:id')->get();

        $alloc = [];
        foreach ($groups as $group) {
            $lines = [];
            foreach ($group->products as $product) {
                $pid = $product->id;
                // Producto con promo propia o ya asignado por otra familia: se saltea.
                if (in_array($pid, $conPromoPropia, true) || isset($alloc[$pid])) {
                    continue;
                }
                if ($line = $cartByProduct->get($pid)) {
                    $lines[] = ['product_id' => $pid, 'quantity' => (int) $line['quantity'], 'unit_price' => (float) $line['unit_price']];
                }
            }

            if ($lines === []) {
                continue;
            }

            foreach (PromotionEngine::groupDiscount($group->buy_qty, $group->pay_qty, $lines) as $pid => $amount) {
                $alloc[$pid] = ['amount' => $amount, 'label' => $group->shortLabel()];
            }
        }

        return collect($alloc);
    }

    /** Descuento en pesos que la promo (propia o de familia) le da a esta línea. */
    public function promoDiscountAmount(array $line): float
    {
        $promo = $this->promoMap->get($line['product_id']);

        if ($promo) {
            return PromotionEngine::discount($promo, (int) $line['quantity'], (float) $line['unit_price']);
        }

        $grupo = $this->groupAllocations->get($line['product_id']);

        return $grupo ? (float) $grupo['amount'] : 0.0;
    }

    /** Etiqueta corta de la promo aplicada a la línea (o null). */
    public function promoLabel(array $line): ?string
    {
        $promo = $this->promoMap->get($line['product_id']);

        if ($promo) {
            return $this->promoDiscountAmount($line) > 0 ? $promo->shortLabel() : null;
        }

        $grupo = $this->groupAllocations->get($line['product_id']);

        return $grupo && $grupo['amount'] > 0 ? $grupo['label'] : null;
    }

    /**
     * Descuento efectivo (%) de la línea: combina el descuento manual con el
     * de la promoción. Es lo que se guarda como discount_percent en la factura.
     */
    public function lineDiscountPct(array $line): float
    {
        $gross = (float) $line['unit_price'] * (int) $line['quantity'];

        if ($gross <= 0) {
            return (float) ($line['discount'] ?? 0);
        }

        // max(0, ...) también en $manual: un descuento negativo (llegado por
        // fuera del input normal, que solo permite 0-100) no puede convertirse
        // en un recargo — como mucho, descuento 0.
        $manual = max(0, $gross * (float) ($line['discount'] ?? 0) / 100);
        $descuento = max(0, min($gross, $manual + $this->promoDiscountAmount($line)));

        return round($descuento / $gross * 100, 4);
    }

    /** Total de una línea (con descuentos y IVA). */
    public function lineTotal(array $line): float
    {
        $gross = (float) $line['unit_price'] * (int) $line['quantity'];
        $neto = $gross * (1 - $this->lineDiscountPct($line) / 100);

        return $neto * (1 + (float) $line['iva_rate'] / 100);
    }

    public function total(): float
    {
        return collect($this->cart)->sum(fn ($i) => $this->lineTotal($i));
    }

    /** Subtotal sin ningún descuento (con IVA), para mostrar el ahorro. */
    public function subtotalBruto(): float
    {
        return collect($this->cart)->sum(
            fn ($i) => (float) $i['unit_price'] * (int) $i['quantity'] * (1 + (float) $i['iva_rate'] / 100)
        );
    }

    /** Total de descuentos aplicados (manual + promos), con IVA. */
    public function descuentosTotal(): float
    {
        return round($this->subtotalBruto() - $this->total(), 2);
    }

    /**
     * Promos aplicadas en el carrito, agrupadas por etiqueta, para el
     * resumen del final de la pantalla y del ticket.
     *
     * @return array<int, array{label: string, amount: float}>
     */
    public function promosAplicadas(): array
    {
        $acumulado = [];

        foreach ($this->cart as $line) {
            $label = $this->promoLabel($line);

            if (! $label) {
                continue;
            }

            $monto = $this->promoDiscountAmount($line) * (1 + (float) $line['iva_rate'] / 100);
            $acumulado[$label] = ($acumulado[$label] ?? 0) + $monto;
        }

        return collect($acumulado)
            ->map(fn ($amount, $label) => ['label' => $label, 'amount' => round($amount, 2)])
            ->values()
            ->all();
    }

    public function itemsCount(): int
    {
        return collect($this->cart)->sum('quantity');
    }

    /** Suma de lo que se está pagando en el momento (todos los medios cargados). */
    public function paymentsTotal(): float
    {
        return collect($this->payments)->sum(fn ($p) => (float) $p['amount']);
    }

    /** Lo que quedaría como saldo del cliente (total − pagado). Nunca negativo. */
    public function saldoPendiente(): float
    {
        return max(0, round($this->total() - $this->paymentsTotal(), 2));
    }

    /**
     * Agrega un medio de pago prellenado con el saldo que falta cubrir, para
     * que en el caso típico (pago completo en efectivo) sea un solo toque.
     */
    public function addPayment(): void
    {
        $faltante = round($this->total() - $this->paymentsTotal(), 2);

        $this->payments[] = [
            'method' => 'efectivo',
            'amount' => $faltante > 0 ? (string) $faltante : '0',
        ];
    }

    public function removePayment(int $index): void
    {
        unset($this->payments[$index]);
        $this->payments = array_values($this->payments);
    }

    public function cobrar(): void
    {
        if ($this->cart === []) {
            $this->addError('cart', 'Agregá al menos un producto.');

            return;
        }

        $total = round($this->total(), 2);
        $pagado = round($this->paymentsTotal(), 2);

        if ($pagado > $total + 0.001) {
            $this->addError('payments', 'Lo pagado ($'.money($pagado).') supera el total. Ajustá los montos.');

            return;
        }

        $consumidorFinal = Client::consumidorFinal();
        $clientId = $this->client_id ?: $consumidorFinal->id;

        // Si queda saldo, tiene que ir a la cuenta de un cliente real (no a Consumidor Final).
        if ($pagado + 0.001 < $total && $clientId === $consumidorFinal->id) {
            $this->addError('client_id', 'Queda un saldo pendiente: elegí un cliente real para cargarlo a su cuenta corriente.');

            return;
        }

        // Límite de crédito: no dejar que la venta a cuenta corriente lo supere.
        if ($pagado + 0.001 < $total && $clientId !== $consumidorFinal->id) {
            $clienteCC = Client::find($clientId);
            if ($clienteCC && ($excesoMsg = $clienteCC->excesoDeCredito($total - $pagado))) {
                $this->addError('client_id', $excesoMsg);

                return;
            }
        }

        $tipo = TipoComprobanteInterno::tryFrom($this->tipo_comprobante_interno);
        if (! $tipo || ! in_array($tipo, CompanySettings::current()->tiposComprobanteSeleccionables(), true)) {
            $tipo = CompanySettings::current()->tipoComprobantePorDefecto();
        }
        $status = $pagado + 0.001 >= $total ? 'paid' : 'pending';

        $invoice = InvoiceNumberGenerator::withLock($tipo->value, fn () => DB::transaction(function () use ($tipo, $clientId, $status) {
            $invoice = Invoice::create([
                'number' => InvoiceNumberGenerator::next($tipo->value),
                'client_id' => $clientId,
                'tipo_comprobante_interno' => $tipo,
                'issue_date' => now()->toDateString(),
                'due_date' => now()->toDateString(),
                'tax_rate' => 0,
                'status' => $status,
            ]);

            foreach ($this->cart as $item) {
                $invoice->items()->create([
                    'product_id' => $item['product_id'],
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    // Descuento manual + promoción, expresado como % de la línea.
                    'discount_percent' => $this->lineDiscountPct($item),
                    'iva_rate' => $item['iva_rate'],
                ]);
            }

            StockAdjuster::apply($this->cart, $tipo->stockSign());

            foreach ($this->payments as $payment) {
                if ((float) $payment['amount'] > 0) {
                    $created = $invoice->payments()->create($payment);
                    CashLinker::linkInvoicePayment($invoice, $created);
                }
            }

            return $invoice;
        }));

        if ($this->printOnSale) {
            try {
                app(TicketPrinterService::class)->imprimir($invoice);
            } catch (\Throwable $e) {
                session()->flash('error', 'La venta se guardó, pero no se pudo imprimir el ticket: '.$e->getMessage());
            }
        }

        $saldo = round((float) $invoice->total - $pagado, 2);

        $this->cart = [];
        $this->search = '';
        $this->payments = [];
        $this->client_id = $consumidorFinal->id;

        $msg = "Venta {$invoice->number} registrada por $".money((float) $invoice->total).'.';
        if ($saldo > 0) {
            $msg .= ' Saldo en cuenta corriente: $'.money($saldo).'.';
        }
        session()->flash('status', $msg);
    }

    public function render()
    {
        return view('livewire.pos.index', [
            'paymentMethods' => PaymentMethod::cases(),
            'clients' => Client::forSelectCached(),
            'priceLists' => PriceList::active()->orderBy('name')->get(),
            'tipoComprobanteInternoOptions' => CompanySettings::current()->tiposComprobanteSeleccionables(),
        ]);
    }
}
