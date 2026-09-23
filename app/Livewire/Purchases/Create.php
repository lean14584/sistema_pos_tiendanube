<?php

namespace App\Livewire\Purchases;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\TipoComprobante;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Provider;
use App\Models\Purchase;
use App\Support\CashLinker;
use App\Support\CurrentSucursal;
use App\Support\StockAdjuster;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Create extends Component
{
    public string $provider_id = '';

    public string $tipo_comprobante = '1';

    public string $punto_venta = '1';

    public string $numero_comprobante = '1';

    public string $issue_date;

    public string $due_date;

    public string $tax_rate = '0';

    public string $notes = '';

    public string $status = 'draft';

    /**
     * Compra "sin detalle": el proveedor no pasó un desglose de productos
     * (o no vale la pena cargarlo ítem por ítem) — se anota solo el total
     * a mano y no se toca el stock de ningún producto.
     */
    public bool $sin_detalle = false;

    public string $manual_total = '';

    public string $remito_number = '';

    /**
     * Guarda contra doble-submit: mismo criterio que Invoices\Create — este
     * form no tiene ningún estado persistente natural para detectar "esto
     * ya se guardó" (a diferencia de Pos\Index, que vacía el carrito solo).
     * El lock de 'purchase-number' de más abajo solo serializa la
     * NUMERACIÓN (evita números repetidos si dos compras distintas se
     * crean casi a la vez), no evita que dos submits del mismo click doble
     * generen dos compras completas con números distintos.
     */
    public bool $submitted = false;

    /** @var array<int, array{product_id: int, description: string, quantity: string, unit_price: string, batch_number: string, expiration_date: string}> */
    public array $items = [];

    /** @var array<int, array{method: string, amount: string}> */
    public array $payments = [];

    /** @var array<int, array{concepto: string, amount: string}> */
    public array $taxes = [];

    public string $productQuery = '';

    public string $providerQuery = '';

    public function mount(): void
    {
        $this->issue_date = now()->toDateString();
        $this->due_date = now()->addDays(15)->toDateString();
    }

    #[Computed]
    public function productResults()
    {
        $term = trim($this->productQuery);

        if ($term === '') {
            return collect();
        }

        return Product::where('name', 'like', "%{$term}%")
            ->orWhere('sku', 'like', "%{$term}%")
            ->limit(8)
            ->get();
    }

    public function addProductItem(int $productId): void
    {
        $product = Product::findOrFail($productId);

        $this->items[] = [
            'product_id' => $product->id,
            'description' => $product->name,
            'quantity' => '1',
            'unit_price' => (string) $product->price,
            'batch_number' => '',
            'expiration_date' => '',
        ];

        $this->productQuery = '';
    }

    #[Computed]
    public function providerResults()
    {
        $term = trim($this->providerQuery);

        if ($term === '') {
            return collect();
        }

        return Provider::where('name', 'like', "%{$term}%")
            ->orWhere('phone', 'like', "%{$term}%")
            ->orWhere('tax_id', 'like', "%{$term}%")
            ->limit(8)
            ->get();
    }

    public function selectProvider(int $providerId): void
    {
        $this->providerQuery = '';
        $this->provider_id = (string) $providerId;
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    /** Al activar "sin detalle" se descartan los productos ya cargados: no tendría sentido guardarlos junto a un total puesto a mano. */
    public function updatedSinDetalle(bool $value): void
    {
        if ($value) {
            $this->items = [];
        }
    }

    public function subtotal(): float
    {
        return collect($this->items)->sum(fn ($item) => (float) $item['quantity'] * (float) $item['unit_price']);
    }

    public function taxAmount(): float
    {
        return $this->subtotal() * ((float) $this->tax_rate / 100);
    }

    public function percepcionesTotal(): float
    {
        return collect($this->taxes)->sum(fn ($t) => (float) ($t['amount'] ?? 0));
    }

    public function addTax(): void
    {
        $this->taxes[] = ['concepto' => '', 'amount' => ''];
    }

    public function removeTax(int $index): void
    {
        unset($this->taxes[$index]);
        $this->taxes = array_values($this->taxes);
    }

    public function total(): float
    {
        if ($this->sin_detalle) {
            return (float) $this->manual_total;
        }

        return $this->subtotal() + $this->taxAmount() + $this->percepcionesTotal();
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
        Cache::lock('purchases:create:'.CurrentSucursal::id().':'.Auth::id(), 10)->block(5, fn () => $this->saveInterno());
    }

    private function saveInterno(): void
    {
        if ($this->submitted) {
            return;
        }

        $this->validate([
            'provider_id' => ['required', 'exists:providers,id'],
            'tipo_comprobante' => ['required', Rule::enum(TipoComprobante::class)],
            'punto_venta' => ['required', 'integer', 'min:1', 'max:9999'],
            'numero_comprobante' => ['required', 'integer', 'min:1', 'max:99999999'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date'],
            'tax_rate' => ['required', 'numeric', 'min:0'],
            'status' => ['required'],
            'notes' => ['nullable', 'string'],
            'remito_number' => ['nullable', 'string', 'max:60'],
            'manual_total' => [$this->sin_detalle ? 'required' : 'nullable', 'numeric', 'min:0.01'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.batch_number' => ['nullable', 'string', 'max:60'],
            'items.*.expiration_date' => ['nullable', 'date'],
            'taxes.*.concepto' => ['required_with:taxes.*.amount', 'nullable', 'string', 'max:100'],
            'taxes.*.amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (! $this->sin_detalle && empty($this->items)) {
            $this->addError('items', 'Agregá al menos un producto, o marcá "Compra sin detalle" si no vas a cargar productos.');

            return;
        }

        // Si se va a registrar un pago, tiene que quedar anotado en una caja
        // abierta — si no, la plata que sale queda invisible para el arqueo
        // (CashLinker::linkPurchasePayment() no avisa, solo no hace nada).
        $registraMovimientoDeCaja = collect($this->payments)->contains(fn ($p) => (float) $p['amount'] > 0);

        if ($registraMovimientoDeCaja && ! CashLinker::hasOpenSession()) {
            $this->addError('payments', 'Tenés que abrir la caja antes de registrar un pago.');

            return;
        }

        // La sucursal de la compra queda fija a la que estaba activa en
        // este momento (misma sucursal donde StockAdjuster::apply() suma el
        // stock) — Purchases\Edit y Show::delete() la reusan tal cual al
        // revertir/reaplicar, en vez de resolver la sucursal activa de
        // quien esté editando/borrando más adelante (ver migración
        // add_sucursal_id_to_purchases_table).
        $sucursalId = CurrentSucursal::id();

        // Recién acá, pasadas todas las validaciones: una falla de
        // validación legítima no debe dejar al usuario sin poder reintentar.
        $this->submitted = true;

        $purchase = Cache::lock('purchase-number', 10)->block(10, fn () => DB::transaction(function () use ($sucursalId) {
            $purchase = Purchase::create([
                'number' => $this->nextNumber(),
                'provider_id' => $this->provider_id,
                'sucursal_id' => $sucursalId,
                'tipo_comprobante' => $this->tipo_comprobante,
                'punto_venta' => $this->punto_venta,
                'numero_comprobante' => $this->numero_comprobante,
                'issue_date' => $this->issue_date,
                'due_date' => $this->due_date,
                'tax_rate' => $this->tax_rate,
                'notes' => $this->notes ?: null,
                'status' => $this->status,
                'sin_detalle' => $this->sin_detalle,
                'manual_total' => $this->sin_detalle ? $this->manual_total : null,
                'remito_number' => $this->remito_number !== '' ? trim($this->remito_number) : null,
            ]);

            if (! $this->sin_detalle) {
                foreach ($this->items as $item) {
                    $purchase->items()->create($item);
                }
            }

            foreach ($this->taxes as $tax) {
                if (trim((string) $tax['concepto']) !== '' && (float) $tax['amount'] > 0) {
                    $purchase->taxes()->create([
                        'concepto' => trim($tax['concepto']),
                        'amount' => $tax['amount'],
                    ]);
                }
            }

            $itemsParaStock = $this->sin_detalle ? [] : $this->items;

            StockAdjuster::apply($itemsParaStock, 1, $sucursalId);

            foreach ($itemsParaStock as $item) {
                if ($sucursalId !== null && ! empty($item['expiration_date'])) {
                    ProductBatch::create([
                        'product_id' => $item['product_id'],
                        'sucursal_id' => $sucursalId,
                        'purchase_id' => $purchase->id,
                        'batch_number' => $item['batch_number'] !== '' ? $item['batch_number'] : null,
                        'quantity_received' => $item['quantity'],
                        'quantity_remaining' => $item['quantity'],
                        'expiration_date' => $item['expiration_date'],
                    ]);
                }
            }

            foreach ($this->payments as $payment) {
                if ((float) $payment['amount'] > 0) {
                    $created = $purchase->payments()->create($payment);
                    CashLinker::linkPurchasePayment($purchase, $created);
                }
            }

            return $purchase;
        }));

        session()->flash('status', 'Compra registrada.');
        $this->redirect(route('purchases.show', $purchase), navigate: true);
    }

    /**
     * Antes calculaba Purchase::count()+1: si se borraba una compra del
     * medio, el conteo bajaba y el próximo número calculado ya existía,
     * violando el índice único de `number` y perdiendo la compra al
     * guardar. Ahora sigue al último número real, como ya hace
     * InvoiceNumberGenerator para facturas/remitos.
     */
    private function nextNumber(): string
    {
        $last = Purchase::where('number', 'like', 'COM-%')->orderByDesc('id')->value('number');

        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return 'COM-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public function render()
    {
        return view('livewire.purchases.create', [
            'selectedProviderName' => $this->provider_id !== '' ? Provider::find($this->provider_id)?->name : null,
            'statuses' => InvoiceStatus::cases(),
            'tiposComprobante' => TipoComprobante::cases(),
            'paymentMethods' => PaymentMethod::cases(),
        ]);
    }
}
