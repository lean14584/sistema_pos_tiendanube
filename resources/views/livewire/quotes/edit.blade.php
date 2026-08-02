<div class="p-8 max-w-5xl mx-auto">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100 mb-8">Editar presupuesto {{ $quote->number }}</h1>

    @if ($quote->status->value === 'converted')
        <div class="bg-indigo-50 dark:bg-indigo-500/10 border border-indigo-200 dark:border-indigo-500/20 rounded-lg p-4 text-sm text-indigo-800 dark:text-indigo-400">
            Este presupuesto ya fue convertido a una venta y no se puede editar.
            @if ($quote->converted_invoice_id)
                <a href="{{ route('invoices.show', $quote->converted_invoice_id) }}" wire:navigate class="underline font-medium">Ver factura</a>
            @endif
        </div>
    @else
        @include('livewire.quotes._form', ['submitLabel' => 'Guardar cambios'])
    @endif
</div>
