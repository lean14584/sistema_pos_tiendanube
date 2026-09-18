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
use App\Models\Voucher;
use App\Support\CashLinker;
use App\Support\CurrentSucursal;
use App\Support\InvoiceNumberGenerator;
use App\Support\PromotionEngine;
use App\Support\ScaleBarcodeParser;
use App\Support\StockAdjuster;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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

    /** Si está tildado, al final del ticket normal se agrega un cupón de
     * cambio (artículo + importe + medio de pago + "7 días hábiles para el
     * cambio"). No hace nada si printOnSale está destildado. */
    public bool $printExchangeSlip = false;

    /** Tipo de comprobante a generar (Factura A/B, Remito X, etc.). */
    public string $tipo_comprobante_interno = '';

    /** Vacío = usar el único/por defecto de la sucursal activa (no se muestra selector). */
    public string $punto_venta = '';

    // --- Cambio (devolución + producto nuevo en la misma operación) ---

    public string $numeroFacturaOrigen = '';

    /** Factura/remito original del que se están devolviendo ítems, o null. */
    public ?Invoice $facturaOrigen = null;

    /** Cuando el número ingresado matchea más de un comprobante (mismo número, distinto tipo). */
    public Collection $facturaOrigenCandidatas;

    /** @var array<int, array{product_id: ?int, description: string, quantity: string, unit_price: string, iva_rate: string}> */
    public array $itemsADevolver = [];

    // --- Vale de cambio ---

    public string $vale_codigo = '';

    public string $vale_monto = '';

    public ?Voucher $valeEncontrado = null;

    public function mount(): void
    {
        $cf = Client::consumidorFinal();
        $this->client_id = $cf->id;
        $this->price_list_id = $cf->price_list_id; // null = precio base
        $this->tipo_comprobante_interno = CompanySettings::current()->tipoComprobantePorDefecto()->value;
        $this->facturaOrigenCandidatas = collect();

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

        // Con 1 solo caracter el LIKE '%x%' escanea toda la tabla en cada
        // tecla sin acotar casi nada el resultado — se pide un mínimo.
        if (mb_strlen($term) < 2) {
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

        // Con 1 solo caracter el LIKE '%x%' escanea toda la tabla en cada
        // tecla (cada 200ms) sin acotar casi nada el resultado — se pide un
        // mínimo, igual criterio que clientResults().
        if (mb_strlen($term) < 2) {
            return collect();
        }

        // ->with('stocks') evita 1 query de stock por cada uno de los hasta
        // 8 resultados mostrados (ver stockEnSucursal() en la vista): con
        // la relación ya cargada, resuelve el stock de la sucursal activa
        // en memoria en vez de ir a la base por cada fila.
        return Product::where('name', 'like', "%{$term}%")
            ->orWhere('sku', 'like', "%{$term}%")
            ->with('stocks')
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
            'iva_rate' => CompanySettings::current()->debeOcultarIvaPorItem() ? '0' : AlicuotaIva::normalizar($product->iva_rate),
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

        // findByBarcode() cubre el sku tal cual y las dos variantes de
        // código impreso por la etiqueta (EAN13 completo y su equivalente
        // UPC-A de 12 dígitos, ver Product::findByBarcode). El nombre exacto
        // es un fallback aparte, propio de esta pantalla.
        $product = Product::findByBarcode($code)
            ?? Product::where('name', $code)->first();

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
            'iva_rate' => CompanySettings::current()->debeOcultarIvaPorItem() ? '0' : AlicuotaIva::normalizar($product->iva_rate),
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

    /** Monto a mostrar en el botón "Cobrar": la diferencia neta de crédito en un cambio, o el total normal. */
    public function montoACobrar(): float
    {
        if ($this->itemsADevolver !== []) {
            return max(0, $this->diferencia());
        }

        return $this->totalConDescuentoPorMedioDePago() ?? $this->total();
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
        $faltante = round($this->objetivoACobrar() - $this->paymentsTotal() - $this->valeMontoPendiente(), 2);

        $this->payments[] = [
            'method' => '',
            'amount' => $faltante > 0 ? (string) $faltante : '0',
        ];
    }

    /** Lo que hay que cubrir con pagos + vale: la diferencia neta de crédito en un cambio, o el total normal. */
    private function objetivoACobrar(): float
    {
        return $this->itemsADevolver !== [] ? max(0, $this->diferencia()) : round($this->total(), 2);
    }

    /** Monto del vale ya seleccionado (aunque todavía no se haya confirmado el cobro). */
    private function valeMontoPendiente(): float
    {
        return $this->valeEncontrado ? (float) $this->vale_monto : 0.0;
    }

    public function removePayment(int $index): void
    {
        unset($this->payments[$index]);
        $this->payments = array_values($this->payments);
    }

    /**
     * Busca el comprobante original por número (para el modo Cambio: se
     * dispara al elegir "Devolución" en el tipo de comprobante). Excluye
     * Notas de Crédito y Devoluciones — no tiene sentido "devolver" contra
     * uno de esos. El número es único por (tipo, número), así que puede
     * haber más de un comprobante con el mismo número si son de tipos
     * distintos — en ese caso se deja elegir.
     */
    public function buscarFacturaOrigen(): void
    {
        $this->resetErrorBag('numeroFacturaOrigen');
        $this->facturaOrigenCandidatas = collect();

        $numero = trim($this->numeroFacturaOrigen);

        if ($numero === '') {
            return;
        }

        $candidatas = Invoice::where('number', $numero)
            ->whereNotIn('tipo_comprobante_interno', [
                TipoComprobanteInterno::Devolucion,
                TipoComprobanteInterno::NotaCreditoA,
                TipoComprobanteInterno::NotaCreditoB,
                TipoComprobanteInterno::NotaCreditoC,
            ])
            ->with('items')
            ->get();

        if ($candidatas->isEmpty()) {
            $this->addError('numeroFacturaOrigen', "No se encontró ningún comprobante con el número «{$numero}».");

            return;
        }

        if ($candidatas->count() > 1) {
            $this->facturaOrigenCandidatas = $candidatas;

            return;
        }

        $this->cargarFacturaOrigen($candidatas->first());
    }

    public function elegirFacturaOrigen(int $invoiceId): void
    {
        $invoice = $this->facturaOrigenCandidatas->firstWhere('id', $invoiceId);

        if ($invoice) {
            $this->cargarFacturaOrigen($invoice);
        }
    }

    private function cargarFacturaOrigen(Invoice $invoice): void
    {
        $this->facturaOrigen = $invoice;
        $this->facturaOrigenCandidatas = collect();

        $this->itemsADevolver = $invoice->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'description' => $item->description,
            'quantity' => (string) $item->quantity,
            'unit_price' => (string) $item->unit_price,
            'iva_rate' => AlicuotaIva::normalizar($item->iva_rate_efectiva),
        ])->all();
    }

    public function quitarFacturaOrigen(): void
    {
        $this->facturaOrigen = null;
        $this->numeroFacturaOrigen = '';
        $this->facturaOrigenCandidatas = collect();
        $this->itemsADevolver = [];
    }

    public function removeItemADevolver(int $index): void
    {
        unset($this->itemsADevolver[$index]);
        $this->itemsADevolver = array_values($this->itemsADevolver);
    }

    /** Neto de lo que se devuelve (cantidad x precio, tal como estaban en el comprobante original). */
    public function creditoSubtotal(): float
    {
        return collect($this->itemsADevolver)->sum(fn ($i) => (float) $i['quantity'] * (float) $i['unit_price']);
    }

    public function creditoTaxAmount(): float
    {
        return collect($this->itemsADevolver)->sum(
            fn ($i) => (float) $i['quantity'] * (float) $i['unit_price'] * ((float) $i['iva_rate'] / 100)
        );
    }

    /** Valor total (con IVA) de lo que se devuelve — mismo criterio que total() para el carrito nuevo. */
    public function creditoTotal(): float
    {
        return $this->creditoSubtotal() + $this->creditoTaxAmount();
    }

    /** Positivo: falta cobrar. Negativo: sobra a favor del cliente (se emite vale por eso). */
    public function diferencia(): float
    {
        return round($this->total() - $this->creditoTotal(), 2);
    }

    public function buscarVale(): void
    {
        $this->resetErrorBag('vale_codigo');

        $voucher = Voucher::porCodigo($this->vale_codigo);

        if (! $voucher || ! $voucher->estaDisponible()) {
            $this->valeEncontrado = null;
            $this->addError('vale_codigo', 'Ese código de vale no existe o ya no tiene saldo.');

            return;
        }

        $this->valeEncontrado = $voucher;

        // Prellenado con lo que realmente hace falta cubrir (nunca más que
        // el saldo del vale ni más que lo que falta pagar).
        $faltante = max(0, round($this->objetivoACobrar() - $this->paymentsTotal(), 2));
        $this->vale_monto = (string) min((float) $voucher->balance, $faltante);
    }

    public function quitarVale(): void
    {
        $this->valeEncontrado = null;
        $this->vale_codigo = '';
        $this->vale_monto = '';
    }

    public function cobrar(): void
    {
        // MEJORA: sin este lock, un doble clic en "Cobrar" (o dos pestañas
        // del mismo cajero) podía disparar dos requests casi simultáneas que
        // pasaban los mismos chequeos y generaban dos facturas del mismo
        // carrito — InvoiceNumberGenerator::withLock() más abajo solo
        // serializa la numeración (evita números repetidos), no evita la
        // duplicación en sí. El lock es por (sucursal, usuario), igual que
        // CashRegister::openSession(): serializa las dos llamadas, y como
        // $this->cart se vacía recién al final de una venta exitosa, la
        // segunda llamada encuentra el carrito ya vacío y corta sola en el
        // chequeo de abajo.
        Cache::lock('pos:cobrar:'.CurrentSucursal::id().':'.Auth::id(), 10)->block(5, fn () => $this->cobrarInterno());
    }

    private function cobrarInterno(): void
    {
        $modoCambio = $this->itemsADevolver !== [];

        // Devolución pura (sin producto nuevo): solo repone stock y, si
        // corresponde, emite un vale por el total — no pasa por el flujo de
        // pago/carrito de una venta.
        if ($modoCambio && $this->cart === []) {
            $this->procesarDevolucionPura();

            return;
        }

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

        $cartTotal = round($this->total(), 2);

        // Crédito por lo devuelto, aplicado contra el carrito nuevo. Si
        // sobra crédito (devuelve más de lo que se lleva), lo que falta
        // cobrar es 0 y el sobrante se resuelve como vale más abajo.
        $creditoTotal = $modoCambio ? round($this->creditoTotal(), 2) : 0.0;
        $total = max(0, round($cartTotal - $creditoTotal, 2));

        $voucherAplicado = $this->valeEncontrado;
        $valeMonto = $voucherAplicado
            ? max(0, round(min((float) $this->vale_monto, (float) $voucherAplicado->balance), 2))
            : 0.0;

        $pagado = round($this->paymentsTotal(), 2) + $valeMonto;

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

        // En un cambio, lo que se lleva es SIEMPRE una venta normal (resta
        // stock) — el tipo elegido en el selector ("Devolución") fue solo
        // el gatillo para pedir el número de factura, no el tipo real del
        // comprobante que sale por lo nuevo.
        if ($modoCambio) {
            $tipo = CompanySettings::current()->tipoComprobantePorDefecto();
        } else {
            $tipo = TipoComprobanteInterno::tryFrom($this->tipo_comprobante_interno);
            if (! $tipo || ! in_array($tipo, CompanySettings::current()->tiposComprobanteSeleccionables(), true)) {
                $tipo = CompanySettings::current()->tipoComprobantePorDefecto();
            }
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
        // corriente, esa parte se factura siempre a precio de lista. En un
        // cambio no se aplica: la base de comparación de ese método es el
        // total del carrito nuevo, no la diferencia ya neta de crédito, así
        // que mezclarlo ahí daría un descuento mal calculado.
        if ($modoCambio) {
            $aplicaDescuentoPorMedioDePago = false;
            $descuentoPctPagoGlobal = 0.0;
        } else {
            $totalConDescuento = $this->totalConDescuentoPorMedioDePago();
            $aplicaDescuentoPorMedioDePago = $totalConDescuento !== null;
            $descuentoPctPagoGlobal = $aplicaDescuentoPorMedioDePago && $total > 0
                ? round((1 - $totalConDescuento / $total) * 100, 4)
                : 0.0;
        }

        $facturaOrigenSnapshot = $this->facturaOrigen;
        $itemsADevolverSnapshot = $this->itemsADevolver;

        $invoice = InvoiceNumberGenerator::withLock($tipo->value, fn () => DB::transaction(function () use (
            $tipo, $clientId, $status, $puntoVentaNumero, $descuentoPctPagoGlobal, $aplicaDescuentoPorMedioDePago,
            $modoCambio, $creditoTotal, $cartTotal, $facturaOrigenSnapshot, $itemsADevolverSnapshot,
            $valeMonto, $voucherAplicado,
        ) {
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

            $devolucionInvoice = null;

            if ($modoCambio) {
                // Línea sintética negativa: así $invoice->total (ver
                // HasBillingTotals) ya sale neto del crédito, sin tocar la
                // lógica de cta cte / límite de crédito / saldo que confía
                // en ese accessor en todos lados.
                $montoAplicado = min($creditoTotal, $cartTotal);

                if ($montoAplicado > 0) {
                    $invoice->items()->create([
                        'product_id' => null,
                        'description' => 'Crédito por devolución'.($facturaOrigenSnapshot ? " {$facturaOrigenSnapshot->number}" : ''),
                        'quantity' => 1,
                        'unit_price' => -$montoAplicado,
                        'discount_percent' => 0,
                        'iva_rate' => 0,
                    ]);
                }

                // Devolución hermana: repone stock de lo devuelto. Sin pagos
                // propios — la resolución financiera de la diferencia vive
                // toda en la Venta de arriba (o en el vale, si sobra).
                $devolucionInvoice = InvoiceNumberGenerator::withLock(
                    TipoComprobanteInterno::Devolucion->value,
                    fn () => $this->crearInvoiceDevolucion($facturaOrigenSnapshot, $itemsADevolverSnapshot, $puntoVentaNumero, $clientId),
                    null,
                    $puntoVentaNumero,
                );

                $invoice->update(['cambio_devolucion_id' => $devolucionInvoice->id]);
            }

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

                    // Una Devolución es plata que SALE de la caja, no que
                    // entra — a diferencia de una venta normal. Mismo
                    // criterio que Invoices\Create::save().
                    $tipo === TipoComprobanteInterno::Devolucion
                        ? CashLinker::linkInvoiceRefund($invoice, $created)
                        : CashLinker::linkInvoicePayment($invoice, $created);
                }
            }

            if ($valeMonto > 0 && $voucherAplicado) {
                $voucherAplicado->redeem($valeMonto);
                $invoice->payments()->create([
                    'method' => PaymentMethod::Otro,
                    'amount' => $valeMonto,
                    'voucher_id' => $voucherAplicado->id,
                ]);
                // Sin CashLinker acá: canjear un vale no mete plata física en
                // la caja, es una obligación que ya se había asumido cuando
                // se emitió.
            }

            $voucherEmitido = null;
            if ($modoCambio) {
                $sobrante = max(0, round($creditoTotal - $cartTotal, 2));
                if ($sobrante > 0) {
                    $voucherEmitido = Voucher::emitir($sobrante, CurrentSucursal::id(), $devolucionInvoice);
                }
            }

            return [$invoice, $voucherEmitido];
        }), null, $puntoVentaNumero);

        [$invoice, $voucherEmitido] = $invoice;

        if ($this->printOnSale) {
            $cambio = $this->printExchangeSlip ? 1 : null;
            $escposUrl = URL::temporarySignedRoute('invoices.ticket-escpos', now()->addMinutes(2), ['invoice' => $invoice, 'cambio' => $cambio]);
            $this->js('printTicket('
                .json_encode(route('invoices.ticket-print', ['invoice' => $invoice, 'cambio' => $cambio])).','
                .json_encode($escposUrl)
                .')');
        }

        $saldo = round((float) $invoice->total - $pagado, 2);

        $this->cart = [];
        $this->payments = [];
        $this->client_id = $consumidorFinal->id;
        $this->quitarFacturaOrigen();
        $this->quitarVale();

        $msg = "Venta {$invoice->number} registrada por $".money((float) $invoice->total).'.';
        if ($saldo > 0) {
            $msg .= ' Saldo en cuenta corriente: $'.money($saldo).'.';
        }
        if ($voucherEmitido) {
            $msg .= " Se generó el vale {$voucherEmitido->code} por $".money((float) $voucherEmitido->amount).'.';
        }
        session()->flash('status', $msg);
    }

    /**
     * Devolución sin producto nuevo: repone stock de lo devuelto y, si hay
     * crédito, lo convierte directo en un vale (no hay nada contra qué
     * aplicarlo).
     */
    private function procesarDevolucionPura(): void
    {
        if (! $this->hasOpenCashSession()) {
            $this->addError('cart', 'Tenés que abrir la caja antes de procesar la devolución.');

            return;
        }

        $puntoVentaNumero = $this->puntosVentaOpciones()->firstWhere('numero', (int) $this->punto_venta)?->numero;

        if ($puntoVentaNumero === null) {
            $this->addError('punto_venta', 'Elegí un punto de venta válido.');

            return;
        }

        $creditoTotal = round($this->creditoTotal(), 2);
        $facturaOrigenSnapshot = $this->facturaOrigen;
        $itemsADevolverSnapshot = $this->itemsADevolver;
        $clientId = $this->client_id ?: Client::consumidorFinal()->id;

        [$devolucion, $voucherEmitido] = InvoiceNumberGenerator::withLock(
            TipoComprobanteInterno::Devolucion->value,
            fn () => DB::transaction(function () use ($facturaOrigenSnapshot, $itemsADevolverSnapshot, $puntoVentaNumero, $clientId, $creditoTotal) {
                $devolucion = $this->crearInvoiceDevolucion($facturaOrigenSnapshot, $itemsADevolverSnapshot, $puntoVentaNumero, $clientId);

                $voucher = $creditoTotal > 0 ? Voucher::emitir($creditoTotal, CurrentSucursal::id(), $devolucion) : null;

                return [$devolucion, $voucher];
            }),
            null,
            $puntoVentaNumero,
        );

        $this->quitarFacturaOrigen();

        $msg = "Devolución {$devolucion->number} registrada.";
        if ($voucherEmitido) {
            $msg .= " Se generó el vale {$voucherEmitido->code} por $".money((float) $voucherEmitido->amount).'.';
        }
        session()->flash('status', $msg);
    }

    /**
     * Crea el comprobante de Devolución (repone stock) por los ítems
     * devueltos. Tiene que llamarse ya adentro de un
     * InvoiceNumberGenerator::withLock(Devolucion, ...) — no numera con lock
     * propio.
     *
     * @param  array<int, array{product_id: ?int, description: string, quantity: string, unit_price: string, iva_rate: string}>  $items
     */
    private function crearInvoiceDevolucion(?Invoice $facturaOrigen, array $items, int $puntoVentaNumero, int $clientId): Invoice
    {
        $devolucion = Invoice::create([
            'number' => InvoiceNumberGenerator::next(TipoComprobanteInterno::Devolucion->value, null, $puntoVentaNumero),
            'client_id' => $clientId,
            'punto_venta' => $puntoVentaNumero,
            'tipo_comprobante_interno' => TipoComprobanteInterno::Devolucion,
            'related_invoice_id' => $facturaOrigen?->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'tax_rate' => 0,
            'status' => 'paid',
        ]);

        foreach ($items as $item) {
            $devolucion->items()->create([
                'product_id' => $item['product_id'],
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'iva_rate' => $item['iva_rate'],
            ]);
        }

        StockAdjuster::apply($items, TipoComprobanteInterno::Devolucion->stockSign());

        return $devolucion;
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
