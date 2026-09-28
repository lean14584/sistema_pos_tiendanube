<?php

namespace App\Livewire\Concerns;

/**
 * Cálculo de neto/IVA/total sobre un array de ítems en memoria. Compartido
 * por Invoices\Concerns\ManagesInvoiceLines y NotasCredito\Create — antes
 * estaba copiado byte a byte en los tres lugares (Invoices\Create, Edit y
 * NotasCredito\Create); cualquier cambio a una regla de cálculo (redondeo,
 * descuento, alícuotas) había que acordarse de aplicarlo en cada uno.
 *
 * Requiere que la clase que lo use declare `array $items`, con al menos
 * quantity/unit_price/iva_rate por ítem (discount es opcional: si no está,
 * `lineNeto()` la trata como 0, que es el caso de NotasCredito).
 */
trait CalculatesInvoiceTotals
{
    /** Neto de una línea: cantidad x precio, menos el descuento de la línea (si tiene). */
    private function lineNeto(array $item): float
    {
        return (float) $item['quantity'] * (float) $item['unit_price'] * (1 - (float) ($item['discount'] ?? 0) / 100);
    }

    /**
     * Ítems que realmente se van a persistir al guardar. Antes los
     * totales/validaciones se calculaban sobre TODOS los ítems, incluido uno
     * recién agregado al que todavía no se le tipeó la descripción — ese
     * ítem contaba para el total mostrado pero al guardar se descartaba en
     * silencio, dejando el comprobante persistido con un total menor a lo
     * cobrado/acreditado.
     */
    private function validItems(): \Illuminate\Support\Collection
    {
        return collect($this->items)->filter(fn ($item) => trim($item['description'] ?? '') !== '');
    }

    public function subtotal(): float
    {
        return $this->validItems()->sum(fn ($item) => $this->lineNeto($item));
    }

    public function netoGravado(): float
    {
        return $this->validItems()
            ->filter(fn ($item) => (float) ($item['iva_rate'] ?? 0) > 0)
            ->sum(fn ($item) => $this->lineNeto($item));
    }

    public function netoExento(): float
    {
        return $this->validItems()
            ->filter(fn ($item) => (float) ($item['iva_rate'] ?? 0) <= 0)
            ->sum(fn ($item) => $this->lineNeto($item));
    }

    public function taxAmount(): float
    {
        return $this->validItems()->sum(
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
        return $this->validItems()
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
}
