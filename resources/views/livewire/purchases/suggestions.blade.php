<div class="p-8 max-w-5xl mx-auto">
    <x-page-header title="Sugerencias de compra" subtitle="Productos con stock bajo, priorizados por mayor venta en los últimos {{ $lookbackDays }} días" icon="clipboard-document-check">
        <x-slot:actions>
        <a
            href="{{ route('purchases.create') }}"
            wire:navigate
            class="inline-flex items-center gap-2 rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-emerald-900/20 hover:bg-emerald-400 active:scale-[0.98] transition-all"
        >
            <x-heroicon-o-plus class="w-4 h-4" />
            Nueva compra
        </a>
        </x-slot:actions>
    </x-page-header>

    <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40">
        @if ($suggestions->isEmpty())
            <div class="p-12 text-center text-gray-400 dark:text-gray-500">
                <x-heroicon-o-clipboard-document-check class="w-10 h-10 mx-auto mb-3 text-gray-300 dark:text-gray-700" />
                <p class="text-sm">El stock proyectado alcanza para los próximos {{ $coverageDays }} días.</p>
            </div>
        @else
            <div class="hidden sm:block overflow-x-auto">
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
            </div>

            <div class="sm:hidden divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($suggestions as $row)
                    @php $product = $row['product']; @endphp
                    <div wire:key="suggestion-card-{{ $product->id }}" class="p-4 {{ $product->stock < $product->min_stock ? 'bg-red-50/70 dark:bg-red-500/10' : '' }}">
                        <div class="flex items-start justify-between gap-3 mb-2">
                            <div class="min-w-0 flex items-start gap-2">
                                <span class="shrink-0 mt-0.5 w-5 h-5 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 text-[11px] font-semibold flex items-center justify-center">{{ $loop->iteration }}</span>
                                <div class="min-w-0">
                                    <p class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ $product->name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $product->category?->name ?? '—' }}</p>
                                </div>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="text-[11px] text-gray-400 dark:text-gray-500">Sugerido</p>
                                <p class="font-semibold text-indigo-600 dark:text-indigo-400">{{ $row['suggestedQty'] }}</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-2 text-sm pl-7">
                            <div>
                                <p class="text-[11px] text-gray-400 dark:text-gray-500">Stock / mín.</p>
                                <p class="{{ $product->stock < $product->min_stock ? 'font-medium text-red-600 dark:text-red-400' : 'text-gray-700 dark:text-gray-300' }}">
                                    {{ $product->stock }} <span class="text-gray-400 dark:text-gray-500">/ {{ $product->min_stock }}</span>
                                </p>
                            </div>
                            <div>
                                <p class="text-[11px] text-gray-400 dark:text-gray-500">Vendido ({{ $lookbackDays }}d)</p>
                                <p class="font-medium text-gray-900 dark:text-gray-100">{{ rtrim(rtrim(number_format($row['soldQty'], 2), '0'), '.') }}</p>
                            </div>
                            <div class="min-w-0">
                                <p class="text-[11px] text-gray-400 dark:text-gray-500">Proveedor</p>
                                <p class="text-gray-600 dark:text-gray-400 truncate">{{ $row['lastProvider'] ?? '—' }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
