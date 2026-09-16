<?php

namespace App\Livewire\Invoices\Concerns;

use App\Enums\AlicuotaIva;
use App\Enums\PaymentMethod;
use App\Models\CompanySettings;
use App\Models\Product;

/**
 * Compartido por Invoices\Create e Invoices\Edit: manejo de los ítems y pagos
 * del formulario (antes de guardar) y el cálculo de neto/IVA/total sobre esos
 * arrays en memoria. Antes estaba copiado byte a byte en los dos componentes
 * — cualquier cambio a una regla de cálculo (redondeo, descuento, alícuotas)
 * había que acordarse de aplicarlo dos veces.
 *
 * Requiere que la clase que lo use declare `array $items` (con
 * quantity/unit_price/discount/iva_rate por ítem) y `array $payments`.
 */
trait ManagesInvoiceLines
{
    /** Neto de una línea: cantidad x precio, menos el descuento de la línea. */
    private function lineNeto(array $item): float
    {
        return (float) $item['quantity'] * (float) $item['unit_price'] * (1 - (float) ($item['discount'] ?? 0) / 100);
    }

    public function addProductItem(int $productId): void
    {
        $product = Product::findOrFail($productId);

        $this->items[] = [
            'product_id' => $product->id,
            'description' => $product->name,
            'quantity' => '1',
            'unit_price' => (string) $product->priceForList($this->currentPriceList()),
            'discount' => '0',
            'iva_rate' => CompanySettings::current()->debeOcultarIvaPorItem() ? '0' : AlicuotaIva::normalizar($product->iva_rate),
        ];

        $this->productQuery = '';
    }

    public function addFreeformItem(): void
    {
        $this->items[] = [
            'product_id' => null,
            'description' => '',
            'quantity' => '1',
            'unit_price' => '0',
            'discount' => '0',
            'iva_rate' => CompanySettings::current()->debeOcultarIvaPorItem() ? '0' : '21',
        ];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function subtotal(): float
    {
        return collect($this->items)->sum(fn ($item) => $this->lineNeto($item));
    }

    public function netoGravado(): float
    {
        return collect($this->items)
            ->filter(fn ($item) => (float) ($item['iva_rate'] ?? 0) > 0)
            ->sum(fn ($item) => $this->lineNeto($item));
    }

    public function netoExento(): float
    {
        return collect($this->items)
            ->filter(fn ($item) => (float) ($item['iva_rate'] ?? 0) <= 0)
            ->sum(fn ($item) => $this->lineNeto($item));
    }

    public function taxAmount(): float
    {
        return collect($this->items)->sum(
            fn ($item) => $this->lineNeto($item) * ((float) ($item['iva_rate'] ?? 0) / 100)
        );
    }

    public function total(): float
    {
        return $this->subtotal() + $this->taxAmount();
    }

    /**
     * Desglose del IVA por alícuota para mostrar en el formulario.
     *
     * @return array<int, array{tasa: float, iva: float}>
     */
    public function ivaBreakdown(): array
    {
        return collect($this->items)
            ->filter(fn ($item) => (float) ($item['iva_rate'] ?? 0) > 0)
            ->groupBy(fn ($item) => (string) (float) $item['iva_rate'])
            ->map(fn ($grupo, $tasa) => [
                'tasa' => (float) $tasa,
                'iva' => $grupo->sum(fn ($item) => $this->lineNeto($item) * ((float) $item['iva_rate'] / 100)),
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

    /**
     * % de descuento por pago de contado para un medio de pago cargado en
     * $payments (según la configuración de la empresa). Mismo criterio que
     * Pos\Index — antes esta pantalla nunca lo aplicaba, así que la misma
     * venta cotizaba distinto según se cargara desde acá o desde Venta
     * Rápida con el mismo medio de pago.
     *
     * @param  array{method: string, amount: string}  $payment
     */
    public function paymentDiscountPct(array $payment): float
    {
        $method = PaymentMethod::tryFrom($payment['method'] ?? '');

        return $method ? CompanySettings::current()->descuentoPctParaMedioDePago($method) : 0.0;
    }

    /**
     * Monto real a cobrar en ese medio de pago, ya con su descuento
     * aplicado. Lo tipeado en "amount" es la porción del precio de lista
     * que cubre ese medio.
     *
     * @param  array{method: string, amount: string}  $payment
     */
    public function montoRealPago(array $payment): float
    {
        return round((float) ($payment['amount'] ?? 0) * (1 - $this->paymentDiscountPct($payment) / 100), 2);
    }

    /**
     * Total que termina cobrándose (y facturándose) una vez aplicado el
     * descuento por medio de pago, cuando el comprobante queda pagado por
     * completo en el momento. Si queda saldo pendiente no se aplica ningún
     * descuento — esa parte se factura siempre a precio de lista.
     */
    public function totalConDescuentoPorMedioDePago(): ?float
    {
        $total = round($this->total(), 2);

        if ($total <= 0 || round($this->paidTotal(), 2) + 0.001 < $total) {
            return null;
        }

        $totalReal = round(collect($this->payments)->sum(fn ($p) => $this->montoRealPago($p)), 2);

        return $totalReal < $total ? $totalReal : null;
    }

    /**
     * Combina dos descuentos porcentuales aplicados en cadena (no se suman
     * directo: 20% + 20% no es 40%, es 1-(0.8*0.8) = 36%).
     */
    public function componerDescuentos(float $a, float $b): float
    {
        return round((1 - (1 - $a / 100) * (1 - $b / 100)) * 100, 4);
    }
}
