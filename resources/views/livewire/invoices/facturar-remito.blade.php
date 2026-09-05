<div class="p-8 max-w-3xl mx-auto">
    <a href="{{ route('invoices.show', $remito) }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 mb-6">
        <x-heroicon-o-arrow-left class="w-4 h-4" />
        Volver al remito
    </a>

    <x-page-header title="Facturar remito {{ $remito->number }}" subtitle="Se genera una factura para {{ $remito->client->name }} copiando los ítems del remito. El stock no se vuelve a descontar (ya lo hizo el remito)." icon="document-text" />

    <form wire:submit="save" class="space-y-6">
        <div class="max-w-xs">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipo de factura</label>
            @if (count($tiposDestino) > 0)
                <select wire:model="tipo_comprobante_interno" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    @foreach ($tiposDestino as $t)
                        <option value="{{ $t->value }}">{{ $t->label() }}</option>
                    @endforeach
                </select>
            @else
                <p class="text-sm text-amber-600 dark:text-amber-400">No hay ningún tipo de factura (A/B) habilitado en Datos de la empresa.</p>
            @endif
            @error('tipo_comprobante_interno') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800/50">
                    <tr class="text-left text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-2 font-medium">Descripción</th>
                        <th class="px-4 py-2 font-medium w-24 text-right">Cant.</th>
                        <th class="px-4 py-2 font-medium w-32 text-right">Precio unit.</th>
                        <th class="px-4 py-2 font-medium w-32 text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($remito->items as $item)
                        <tr class="border-t border-gray-100 dark:border-gray-800">
                            <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ $item->description }}</td>
                            <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</td>
                            <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300">${{ money($item->unit_price) }}</td>
                            <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300">${{ money($item->line_total) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-800/40">
                        <td colspan="3" class="px-4 py-2 text-right font-medium text-gray-700 dark:text-gray-300">Total</td>
                        <td class="px-4 py-2 text-right font-semibold text-gray-900 dark:text-gray-100">${{ money($this->total()) }}</td>
                    </tr>
                </tfoot>
            </table>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" @disabled(count($tiposDestino) === 0) class="rounded-lg bg-gradient-to-r from-indigo-600 to-indigo-500 px-4 py-2 text-sm font-medium text-white shadow-md shadow-indigo-600/30 hover:from-indigo-700 hover:to-indigo-600 disabled:opacity-50 disabled:cursor-not-allowed transition-all">
                Generar factura
            </button>
            <a href="{{ route('invoices.show', $remito) }}" wire:navigate class="rounded-lg border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-800">
                Cancelar
            </a>
        </div>
    </form>
</div>
