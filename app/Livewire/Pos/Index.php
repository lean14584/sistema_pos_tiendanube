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
use App\Models\PuntoVenta;
use App\Support\CashLinker;
use App\Support\CurrentSucursal;
use App\Support\Ean13;
use App\Support\InvoiceNumberGenerator;
use App\Support\PromotionEngine;
use App\Support\ScaleBarcodeParser;
use App\Support\StockAdjuster;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
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
    /** @var array<int, array{product_id:int, description:string, sku:?string, unit_price:float, discount:float, iva_rate:string, quantity:float, by_weight?:bool}> */
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

    /** Vacío = usar el único/por defecto de la sucursal activa (no se muestra selector). */
    public string $punto_venta = '';

    public function mount(): void
    {
        $cf = Client::consumidorFinal();
        $this->client_id = $cf->id;
        $this->price_list_id = $cf->price_list_id; // null = precio base
        $this->tipo_comprobante_interno = CompanySettings::current()->tipoComprobantePorDefecto()->value;

        $default = CurrentSucursal::get()?->puntoVentaPorDefecto();
        $this->punto_venta = $default ? (string) $default->numero : '';
    }

    /** Si el usuario actual no tiene una caja abierta en esta sucursal, no puede cobrar. */
    #[Computed]
    public function hasOpenCashSession(): bool
    {
        return CashLinker::hasOpenSession();
    }

    /** Puntos de venta activos de la sucursal donde se está vendiendo. */
    #[Computed]
    public function puntosVentaOpciones()
    {
        $sucursalId = CurrentSucursal::id();

        return $sucursalId
            ? PuntoVenta::where('sucursal_id', $sucursalId)->where('active', true)->orderBy('id')->get()
            : collect();
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
     * Lo dispara el lector de código de barras (o Enter en el buscador). Si
     * el código tiene el formato de balanza configurado (peso variable, ver
     * ScaleBarcodeParser), agrega el producto pesado por el peso leído; si
     * no, busca por SKU exacto o por nombre si es único.
     */
    public function addByBarcode(): void
    {
        $code = trim($this->barcode);
        $this->barcode = '';

        if ($code === '') {
            return;
        }

        $pesado = ScaleBarcodeParser::parse($code, CompanySettings::current());

        if ($pesado) {
            $product = Product::where('sold_by_weight', true)->where('sku', $pesado['sku'])->first();

            if ($product) {
                $this->resetErrorBag('barcode');
                $this->addWeightedProduct($product, $pesado['weightKg']);

                return;
            }
        }

        $product = Product::where('sku', $code)->first()
            ?? Product::where('name', $code)->first();

        // El código de barras que imprime la etiqueta (ver Ean13::fromSku)
        // completa un sku corto con ceros a la izquierda + dígito
        // verificador: si no matcheó tal cual, probar recuperando el sku
        // original antes de darlo por no encontrado.
        if (! $product && strlen($code) === 13) {
            $skuOriginal = Ean13::stripPadding($code);
            $product = $skuOriginal ? Product::where('sku', $skuOriginal)->first() : null;
        }

        if (! $product) {
            $this->addError('barcode', "No se encontró un producto con código «{$code}».");

            return;
        }

        $this->resetErrorBag('barcode');
        $this->addProduct($product->id);
    }

    /**
     * Agrega una línea nueva por cada pesada (no se suma a una línea
     * existente del mismo producto: cada paso por la balanza es una pesada
     * distinta, con su propio peso — a diferencia de escanear el mismo
     * producto de unidad suelta dos veces, que sí incrementa la cantidad).
     */
    private function addWeightedProduct(Product $product, float $weightKg): void
    {
        $this->cart[] = [
            'product_id' => $product->id,
            'description' => $product->name,
            'sku' => $product->sku,
            'unit_price' => $product->priceForList($this->currentPriceList()),
            'discount' => 0,
            'iva_rate' => AlicuotaIva::normalizar($product->iva_rate),
            'quantity' => $weightKg,
            'by_weight' => true,
        ];
    }

    public function inc(int $index): void
    {
        if (isset($this->cart[$index]) && ! ($this->cart[$index]['by_weight'] ?? false)) {
            $this->cart[$index]['quantity']++;
        }
    }

    public function dec(int $index): void
    {
        if (! isset($this->cart[$index]) || ($this->cart[$index]['by_weight'] ?? false)) {
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
        // Los productos pesados quedan afuera de promociones (2x1, NxM):
        // esas promos son de unidades enteras, no de peso variable.
        $ids = collect($this->cart)
            ->reject(fn ($i) => $i['by_weight'] ?? false)
            ->pluck('product_id')->unique()->all();

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
        $cartByProduct = collect($this->cart)->reject(fn ($i) => $i['by_weight'] ?? false)->keyBy('product_id');

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
        $gross = (float) $line['unit_price'] * (float) $line['quantity'];

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

    /**
     * Combina dos descuentos porcentuales aplicados en cadena (no se suman
     * directo: 20% + 20% no es 40%, es 1-(0.8*0.8) = 36%).
     */
    private function componerDescuentos(float $a, float $b): float
    {
        return round((1 - (1 - $a / 100) * (1 - $b / 100)) * 100, 4);
    }

    /** Total de una línea (con descuentos y IVA). */
    public function lineTotal(array $line): float
    {
        $gross = (float) $line['unit_price'] * (float) $line['quantity'];
        $neto = $gross * (1 - $this->lineDiscountPct($line) / 100);

        return $neto * (1 + (float) $line['iva_rate'] / 100);
    }

    public function total(): float
    {
        return collect($this->cart)->sum(fn ($i) => $this->lineTotal($i));
    }

    /**
     * % de descuento por pago de contado para un medio de pago cargado en el
     * array $payments (según la configuración de la empresa).
     *
     * @param  array{method: string, amount: string}  $payment
     */
    public function paymentDiscountPct(array $payment): float
    {
        $method = PaymentMethod::tryFrom($payment['method'] ?? '');

        return $method ? CompanySettings::current()->descuentoPctParaMedioDePago($method) : 0.0;
    }

    /**
     * Monto real a cobrar en ese medio de pago, ya con su descuento aplicado.
     * Lo que se tipea en "amount" es la porción del precio de lista que cubre
     * ese medio — esto es lo que efectivamente hay que recibir en mano.
     *
     * @param  array{method: string, amount: string}  $payment
     */
    public function montoRealPago(array $payment): float
    {
        return round((float) ($payment['amount'] ?? 0) * (1 - $this->paymentDiscountPct($payment) / 100), 2);
    }

    /**
     * Total que termina cobrándose (y facturándose) una vez aplicado el
     * descuento por medio de pago, cuando la venta se paga completa en el
     * momento. Si queda saldo en cuenta corriente no se aplica ningún
     * descuento (ver comentario de cobrar()), y esto devuelve null.
     */
    public function totalConDescuentoPorMedioDePago(): ?float
    {
        $total = round($this->total(), 2);

        if ($total <= 0 || round($this->paymentsTotal(), 2) + 0.001 < $total) {
            return null;
        }

        $totalReal = round(collect($this->payments)->sum(fn ($p) => $this->montoRealPago($p)), 2);

        return $totalReal < $total ? $totalReal : null;
    }

    /** Subtotal sin ningún descuento (con IVA), para mostrar el ahorro. */
    public function subtotalBruto(): float
    {
        return collect($this->cart)->sum(
            fn ($i) => (float) $i['unit_price'] * (float) $i['quantity'] * (1 + (float) $i['iva_rate'] / 100)
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

    /** Cuenta unidades para las líneas normales y 1 "artículo" por cada pesada (no los kg). */
    public function itemsCount(): int
    {
        return (int) collect($this->cart)->sum(fn ($i) => ($i['by_weight'] ?? false) ? 1 : $i['quantity']);
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
     * Agrega una línea de pago con el saldo que falta cubrir prellenado (así
     * el caso típico de pago completo es un solo toque de método + cobrar),
     * pero SIN medio de pago elegido — si hay descuento configurado por
     * medio, no queremos aplicarlo solo porque "efectivo" venía por defecto
     * sin que el cajero lo haya tocado.
     */
    public function addPayment(): void
    {
        $faltante = round($this->total() - $this->paymentsTotal(), 2);

        $this->payments[] = [
            'method' => '',
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

        if (! $this->hasOpenCashSession()) {
            $this->addError('cart', 'Tenés que abrir la caja antes de cobrar.');

            return;
        }

        foreach ($this->payments as $payment) {
            if ((float) ($payment['amount'] ?? 0) > 0 && PaymentMethod::tryFrom($payment['method'] ?? '') === null) {
                $this->addError('payments', 'Elegí un medio de pago para cada monto cargado.');

                return;
            }
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

        // El punto de venta elegido tiene que ser uno de los realmente
        // habilitados para la sucursal activa.
        $puntoVentaNumero = $this->puntosVentaOpciones()->firstWhere('numero', (int) $this->punto_venta)?->numero;

        if ($puntoVentaNumero === null) {
            $this->addError('punto_venta', 'Elegí un punto de venta válido.');

            return;
        }

        // Descuento por medio de pago, expresado como % único que se combina
        // con el descuento propio de cada línea (manual + promo). Solo existe
        // cuando la venta queda pagada por completo en el momento (ver
        // totalConDescuentoPorMedioDePago()) — si queda saldo en cuenta
        // corriente, esa parte se factura siempre a precio de lista.
        $totalConDescuento = $this->totalConDescuentoPorMedioDePago();
        $aplicaDescuentoPorMedioDePago = $totalConDescuento !== null;
        $descuentoPctPagoGlobal = $aplicaDescuentoPorMedioDePago && $total > 0
            ? round((1 - $totalConDescuento / $total) * 100, 4)
            : 0.0;

        $invoice = InvoiceNumberGenerator::withLock($tipo->value, fn () => DB::transaction(function () use ($tipo, $clientId, $status, $puntoVentaNumero, $descuentoPctPagoGlobal, $aplicaDescuentoPorMedioDePago) {
            $invoice = Invoice::create([
                'number' => InvoiceNumberGenerator::next($tipo->value, null, $puntoVentaNumero),
                'client_id' => $clientId,
                'punto_venta' => $puntoVentaNumero,
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
                    // Descuento manual + promoción de la línea, combinado con
                    // el descuento por medio de pago (si corresponde).
                    'discount_percent' => $this->componerDescuentos($this->lineDiscountPct($item), $descuentoPctPagoGlobal),
                    'iva_rate' => $item['iva_rate'],
                ]);
            }

            StockAdjuster::apply($this->cart, $tipo->stockSign());

            foreach ($this->payments as $payment) {
                if ((float) $payment['amount'] > 0) {
                    // Se guarda el monto REAL cobrado (con el descuento del
                    // medio de pago ya aplicado) solo cuando ese descuento
                    // efectivamente corresponde (venta pagada por completo);
                    // si queda saldo en cuenta corriente, el pago se registra
                    // tal cual se tipeó, a precio de lista.
                    $created = $invoice->payments()->create([
                        'method' => $payment['method'],
                        'amount' => $aplicaDescuentoPorMedioDePago ? $this->montoRealPago($payment) : $payment['amount'],
                    ]);
                    CashLinker::linkInvoicePayment($invoice, $created);
                }
            }

            return $invoice;
        }), null, $puntoVentaNumero);

        if ($this->printOnSale) {
            $escposUrl = URL::temporarySignedRoute('invoices.ticket-escpos', now()->addMinutes(2), ['invoice' => $invoice]);
            $this->js('printTicket('
                .json_encode(route('invoices.ticket-print', $invoice)).','
                .json_encode($escposUrl)
                .')');
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
