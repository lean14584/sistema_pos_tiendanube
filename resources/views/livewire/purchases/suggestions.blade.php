<div class="p-8 max-w-5xl mx-auto">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Sugerencias de compra</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Productos con stock bajo, priorizados por mayor venta en los últimos {{ $lookbackDays }} días
            </p>
        </div>
        <a
            href="{{ route('purchases.create') }}"
            wire:navigate
            class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-indigo-600 to-indigo-500 px-4 py-2 text-sm font-medium text-white shadow-md shadow-indigo-600/30 hover:from-indigo-700 hover:to-indigo-600 hover:shadow-lg hover:shadow-indigo-600/40 active:scale-[0.98] transition-all"
        >
            <x-heroicon-o-plus class="w-4 h-4" />
            Nueva compra
        </a>
    </div>

    <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40">
        @if ($suggestions->isEmpty())
            <div class="p-12 text-center text-gray-400 dark:text-gray-500">
                <x-heroicon-o-clipboard-document-check class="w-10 h-10 mx-auto mb-3 text-gray-300 dark:text-gray-700" />
                <p class="text-sm">El stock proyectado alcanza para los próximos {{ $coverageDays }} días.</p>
            </div>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-800 bg-gray-100/80 dark:bg-gray-800/40">
                        <th class="px-5 py-3 font-medium text-right">Prioridad</th>
                        <th class="px-5 py-3 font-medium">Producto</th>
                        <th class="px-5 py-3 font-medium">Categoría</th>
                        <th class="px-5 py-3 font-medium text-right">Stock actual</th>
                        <th class="px-5 py-3 font-medium text-right">Mínimo</th>
                        <th class="px-5 py-3 font-medium text-right">Vendido ({{ $lookbackDays }}d)</th>
                        <th class="px-5 py-3 font-medium text-right">Cantidad sugerida</th>
                        <th class="px-5 py-3 font-medium">Último proveedor</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($suggestions as $row)
                        @php $product = $row['product']; @endphp
                        <tr wire:key="suggestion-{{ $product->id }}" class="border-b border-gray-50 dark:border-gray-800/60 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors {{ $product->stock < $product->min_stock ? 'bg-red-50/70 dark:bg-red-500/10' : '' }}">
                            <td class="px-5 py-3 text-right font-semibold text-gray-400 dark:text-gray-500">{{ $loop->iteration }}</td>
                            <td class="px-5 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $product->name }}</td>
                            <td class="px-5 py-3 text-gray-500 dark:text-gray-400">{{ $product->category?->name ?? '—' }}</td>
                            <td class="px-5 py-3 text-right {{ $product->stock < $product->min_stock ? 'font-medium text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-400' }}">{{ $product->stock }}</td>
                            <td class="px-5 py-3 text-right text-gray-500 dark:text-gray-400">{{ $product->min_stock }}</td>
                            <td class="px-5 py-3 text-right font-semibold text-gray-900 dark:text-gray-100">{{ rtrim(rtrim(number_format($row['soldQty'], 2), '0'), '.') }}</td>
                            <td class="px-5 py-3 text-right font-semibold text-indigo-600 dark:text-indigo-400">{{ $row['suggestedQty'] }}</td>
                            <td class="px-5 py-3 text-gray-500 dark:text-gray-400">{{ $row['lastProvider'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
