<?php

namespace App\Livewire\Invoices\Concerns;

use App\Enums\AlicuotaIva;
use App\Enums\PaymentMethod;
use App\Livewire\Concerns\CalculatesInvoiceTotals;
use App\Models\CompanySettings;
use App\Models\Product;

/**
 * Compartido por Invoices\Create e Invoices\Edit: manejo de los ítems y pagos
 * del formulario (antes de guardar). El cálculo de neto/IVA/total en sí vive
 * en CalculatesInvoiceTotals (compartido a su vez con NotasCredito\Create) —
 * acá solo queda lo específico de una factura: agregar/quitar ítems desde el
 * buscador de productos y el descuento por medio de pago.
 *
 * Requiere que la clase que lo use declare `array $items` (con
 * quantity/unit_price/discount/iva_rate por ítem) y `array $payments`.
 */
trait ManagesInvoiceLines
{
    use CalculatesInvoiceTotals;

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
     * `wire:model.live` en el mismo renglón que el botón de "quitar" (tanto
     * en `items` como en `payments`) puede dejar una entrada a medio
     * construir: si un update ya en vuelo para una fila llega DESPUÉS de que
     * esa fila (u otra anterior) se eliminó y el array se reindexó con
     * `array_values()`, Livewire puede crear una entrada nueva con una sola
     * clave, o pisar la fila que quedó en ese índice con el valor
     * equivocado — mismo mecanismo que rompió `Products\Labels` en
     * producción. Se descarta cualquier entrada incompleta en vez de
     * arriesgar facturar con un ítem o pago a medio llenar.
     */
    public function updated(string $name): void
    {
        if (str_starts_with($name, 'items.')) {
            $this->items = array_values(array_filter(
                $this->items,
                fn (array $i) => isset($i['description'], $i['quantity'], $i['unit_price'], $i['discount'], $i['iva_rate'])
            ));
        }

        if (str_starts_with($name, 'payments.')) {
            $this->payments = array_values(array_filter(
                $this->payments,
                fn (array $p) => isset($p['method'], $p['amount'])
            ));
        }
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
