<div class="p-8 max-w-4xl mx-auto">
    <a href="{{ route('quotes.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 mb-6">
        <x-heroicon-o-arrow-left class="w-4 h-4" />
        Presupuestos
    </a>

    @php $isConverted = $quote->status->value === 'converted'; @endphp

    <div class="flex items-start justify-between mb-8">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $quote->number }}</h1>
                <x-status-badge :status="$quote->status" />
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Emitido el {{ $quote->issue_date->format('d/m/Y') }} · Válido hasta {{ $quote->valid_until->format('d/m/Y') }}
            </p>
        </div>
        @if (! $isConverted)
            <div class="flex gap-2">
                <a href="{{ route('quotes.edit', $quote) }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-800 hover:border-gray-400 dark:hover:border-gray-600 hover:shadow-md active:scale-[0.98] transition-all">
                    <x-heroicon-o-pencil class="w-4 h-4" />
                    Editar
                </a>
                <button
                    wire:click="delete"
                    wire:confirm="¿Eliminar el presupuesto {{ $quote->number }}?"
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-700 px-3 py-2 text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors"
                >
                    <x-heroicon-o-trash class="w-4 h-4" />
                </button>
            </div>
        @endif
    </div>

    @if ($isConverted)
        <div class="bg-indigo-50 dark:bg-indigo-500/10 border border-indigo-200 dark:border-indigo-500/20 rounded-lg p-4 text-sm text-indigo-800 dark:text-indigo-400 mb-6">
            Este presupuesto ya fue convertido a una venta.
            @if ($quote->converted_invoice_id)
                <a href="{{ route('invoices.show', $quote->converted_invoice_id) }}" wire:navigate class="underline font-medium">Ver factura</a>
            @endif
        </div>
    @else
        <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 p-5 mb-6">
            <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3 flex items-center gap-2">
                <x-heroicon-o-arrows-right-left class="w-4 h-4" />
                Pasar a venta
            </h3>
            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                <div class="inline-flex rounded-lg border border-gray-300 dark:border-gray-700 p-1 text-sm">
                    <button
                        type="button"
                        wire:click="$set('priceMode', 'keep')"
                        class="px-3 py-1.5 rounded-md font-medium transition-colors {{ $priceMode === 'keep' ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800' }}"
                    >
                        Mantener precio
                    </button>
                    <button
                        type="button"
                        wire:click="$set('priceMode', 'update')"
                        class="px-3 py-1.5 rounded-md font-medium transition-colors {{ $priceMode === 'update' ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800' }}"
                    >
                        Actualizar presupuesto
                    </button>
                </div>
                <button
                    wire:click="convertToInvoice"
                    wire:confirm="¿Pasar el presupuesto {{ $quote->number }} a una venta?"
                    class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-indigo-600 to-indigo-500 px-4 py-2 text-sm font-medium text-white shadow-md shadow-indigo-600/30 hover:from-indigo-700 hover:to-indigo-600 hover:shadow-lg hover:shadow-indigo-600/40 active:scale-[0.98] transition-all"
                >
                    Convertir a venta
                </button>
            </div>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">
                @if ($priceMode === 'keep')
                    La factura se generará con los precios tal como fueron presupuestados.
                @else
                    Los ítems ligados a un producto tomarán el precio de venta actual del producto.
                @endif
            </p>
        </div>
    @endif

    <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 p-6 mb-6">
        @if (! $isConverted)
            <div class="flex items-center gap-3 mb-6">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Estado:</span>
                <div class="flex gap-1.5">
                    @foreach ($editableStatuses as $s)
                        <button
                            wire:click="setStatus('{{ $s->value }}')"
                            class="px-3 py-1 rounded-full text-xs font-medium transition-colors {{ $quote->status === $s ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700' }}"
                        >
                            {{ $s->label() }}
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-6 pb-6 border-b border-gray-100 dark:border-gray-800">
            <div>
                <p class="text-xs uppercase text-gray-400 dark:text-gray-500 mb-1">Presupuestado a</p>
                <p class="font-medium text-gray-900 dark:text-gray-100">{{ $quote->client->name }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $quote->client->email }}</p>
                @if ($quote->client->address)<p class="text-sm text-gray-500 dark:text-gray-400">{{ $quote->client->address }}</p>@endif
                @if ($quote->client->tax_id)<p class="text-sm text-gray-500 dark:text-gray-400">ID fiscal: {{ $quote->client->tax_id }}</p>@endif
            </div>
        </div>

        <table class="w-full text-sm mb-6">
            <thead>
                <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-800 bg-gray-100/80 dark:bg-gray-800/40">
                    <th class="py-2 font-medium">Descripción</th>
                    <th class="py-2 font-medium text-right">Cant.</th>
                    <th class="py-2 font-medium text-right">Precio unit.</th>
                    <th class="py-2 font-medium text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($quote->items as $item)
                    <tr class="border-b border-gray-50 dark:border-gray-800/60 last:border-0">
                        <td class="py-2.5 text-gray-800 dark:text-gray-200">{{ $item->description }}</td>
                        <td class="py-2.5 text-right text-gray-600 dark:text-gray-400">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</td>
                        <td class="py-2.5 text-right text-gray-600 dark:text-gray-400">${{ number_format($item->unit_price, 2) }}</td>
                        <td class="py-2.5 text-right font-medium text-gray-900 dark:text-gray-100">${{ number_format($item->line_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="flex justify-end">
            <div class="w-full max-w-xs space-y-2 text-sm">
                <div class="flex justify-between text-gray-600 dark:text-gray-400">
                    <span>Subtotal</span>
                    <span>${{ number_format($quote->subtotal, 2) }}</span>
                </div>
                <div class="flex justify-between text-gray-600 dark:text-gray-400">
                    <span>Impuesto ({{ $quote->tax_rate }}%)</span>
                    <span>${{ number_format($quote->tax_amount, 2) }}</span>
                </div>
                <div class="flex justify-between font-semibold text-gray-900 dark:text-gray-100 text-base pt-2 border-t border-gray-200 dark:border-gray-800">
                    <span>Total</span>
                    <span>${{ number_format($quote->total, 2) }}</span>
                </div>
            </div>
        </div>

        @if ($quote->notes)
            <div class="mt-6 pt-6 border-t border-gray-100 dark:border-gray-800">
                <p class="text-xs uppercase text-gray-400 dark:text-gray-500 mb-1">Notas</p>
                <p class="text-sm text-gray-600 dark:text-gray-400 whitespace-pre-wrap">{{ $quote->notes }}</p>
            </div>
        @endif
    </div>
</div>
