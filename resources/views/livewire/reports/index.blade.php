@php
    $barPct = fn ($value, $max) => $max > 0 ? max(2, round(($value / $max) * 100)) : 0;
@endphp

<div class="p-8 max-w-5xl mx-auto">
    <x-page-header title="Informes de ventas" subtitle="Facturas no borrador, agrupadas por distintos criterios" icon="chart-bar">
        <x-slot:actions>
            <a href="{{ route('reports.export.pdf', ['fromDate' => $fromDate, 'toDate' => $toDate]) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-white/15 border border-white/25 px-3 py-2 text-sm font-medium text-white hover:bg-white/25 transition-all">
                <x-heroicon-o-document-arrow-down class="w-4 h-4" /> PDF
            </a>
            <a href="{{ route('reports.export.csv', ['fromDate' => $fromDate, 'toDate' => $toDate]) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-white/15 border border-white/25 px-3 py-2 text-sm font-medium text-white hover:bg-white/25 transition-all">
                <x-heroicon-o-table-cells class="w-4 h-4" /> Excel
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 p-4 mb-6 flex flex-col sm:flex-row sm:items-end gap-3">
        <div>
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Desde</label>
            <input type="date" wire:model.live="fromDate" class="rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
        </div>
        <div>
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Hasta</label>
            <input type="date" wire:model.live="toDate" class="rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
        </div>
        <div class="flex gap-6 sm:ml-auto text-sm">
            <div>
                <p class="text-xs text-gray-400 dark:text-gray-500 uppercase">Total vendido</p>
                <p class="font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-1.5">
                    ${{ number_format($summary['total'], 2) }}
                    @if ($variationPct === null)
                        <span class="text-xs font-normal text-gray-400 dark:text-gray-500">—</span>
                    @else
                        <span class="text-xs font-normal inline-flex items-center gap-0.5 {{ $variationPct >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}" title="vs. período anterior">
                            @if ($variationPct >= 0)
                                <x-heroicon-o-arrow-trending-up class="w-3.5 h-3.5" />
                            @else
                                <x-heroicon-o-arrow-trending-down class="w-3.5 h-3.5" />
                            @endif
                            {{ number_format(abs($variationPct), 1) }}%
                        </span>
                    @endif
                </p>
            </div>
            <div>
                <p class="text-xs text-gray-400 dark:text-gray-500 uppercase">Facturas</p>
                <p class="font-semibold text-gray-900 dark:text-gray-100">{{ $summary['count'] }}</p>
            </div>
        </div>
    </div>

    @if ($summary['count'] === 0)
        <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 p-12 text-center text-gray-400 dark:text-gray-500">
            <x-heroicon-o-chart-bar class="w-10 h-10 mx-auto mb-3 text-gray-300 dark:text-gray-700" />
            <p class="text-sm">No hay ventas en el período seleccionado.</p>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <x-stat-card label="Costo de mercadería" value="${{ number_format($profitability['cost'], 2) }}" icon="cube" accent="text-gray-600 bg-gradient-to-br from-gray-50 to-gray-100/60 dark:text-gray-400 dark:from-gray-500/15 dark:to-gray-500/5" />
            <x-stat-card label="Ganancia bruta" value="${{ number_format($profitability['profit'], 2) }}" icon="banknotes" accent="text-emerald-600 bg-gradient-to-br from-emerald-50 to-emerald-100/60 dark:text-emerald-400 dark:from-emerald-500/15 dark:to-emerald-500/5" />
            <x-stat-card label="Margen" value="{{ number_format($profitability['marginPct'], 1) }}%" icon="chart-bar" accent="text-indigo-600 bg-gradient-to-br from-indigo-50 to-indigo-100/60 dark:text-indigo-400 dark:from-indigo-500/15 dark:to-indigo-500/5" />
        </div>

        <section class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 p-5 mb-6">
            <h2 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-4">Ventas por día</h2>
            <div class="flex items-end gap-1.5 h-40 overflow-x-auto pb-1">
                @foreach ($byDay as $row)
                    <div class="flex flex-col items-center justify-end shrink-0 group" style="min-width: 2rem;">
                        <span class="text-[10px] text-gray-500 dark:text-gray-400 mb-1 opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">${{ number_format($row['total'], 0) }}</span>
                        <div class="w-6 rounded-t bg-indigo-500 dark:bg-indigo-400 hover:bg-indigo-600 dark:hover:bg-indigo-300 transition-colors" style="height: {{ $maxDay > 0 ? max(4, round(($row['total'] / $maxDay) * 120)) : 4 }}px" title="{{ $row['label'] }}: ${{ number_format($row['total'], 2) }} ({{ $row['count'] }} fact.)"></div>
                        <span class="text-[10px] text-gray-500 dark:text-gray-400 mt-1 whitespace-nowrap">{{ $row['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <section class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 p-5">
                <h2 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-4">Ventas por artículo</h2>
                <div class="space-y-3">
                    @foreach ($byArticle as $row)
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-700 dark:text-gray-300 truncate pr-2">{{ $row['label'] }}</span>
                                <span class="text-gray-500 dark:text-gray-400 shrink-0">{{ rtrim(rtrim(number_format($row['quantity'], 2), '0'), '.') }} u. · ${{ number_format($row['total'], 2) }}</span>
                            </div>
                            <div class="h-1.5 w-full rounded-full bg-gray-100 dark:bg-gray-800">
                                <div class="h-1.5 rounded-full bg-indigo-500 dark:bg-indigo-400" style="width: {{ $barPct($row['total'], $maxArticle) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 p-5">
                <h2 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-4">Ventas por categoría</h2>
                <div class="space-y-3">
                    @foreach ($byCategory as $row)
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-700 dark:text-gray-300 truncate pr-2">{{ $row['label'] }}</span>
                                <span class="text-gray-500 dark:text-gray-400 shrink-0">{{ rtrim(rtrim(number_format($row['quantity'], 2), '0'), '.') }} u. · ${{ number_format($row['total'], 2) }}</span>
                            </div>
                            <div class="h-1.5 w-full rounded-full bg-gray-100 dark:bg-gray-800">
                                <div class="h-1.5 rounded-full bg-indigo-500 dark:bg-indigo-400" style="width: {{ $barPct($row['total'], $maxCategory) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 p-5">
                <h2 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-4">Ventas por método de pago</h2>
                <div class="space-y-3">
                    @forelse ($byMethod as $row)
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-700 dark:text-gray-300 truncate pr-2">{{ $row['label'] }}</span>
                                <span class="text-gray-500 dark:text-gray-400 shrink-0">${{ number_format($row['total'], 2) }}</span>
                            </div>
                            <div class="h-1.5 w-full rounded-full bg-gray-100 dark:bg-gray-800">
                                <div class="h-1.5 rounded-full bg-indigo-500 dark:bg-indigo-400" style="width: {{ $barPct($row['total'], $maxMethod) }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400 dark:text-gray-500">Sin pagos registrados en el período.</p>
                    @endforelse
                </div>
            </section>

            <section class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 p-5">
                <h2 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-4">Top clientes</h2>
                <div class="space-y-3">
                    @forelse ($byClient as $row)
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-700 dark:text-gray-300 truncate pr-2">{{ $row['label'] }}</span>
                                <span class="text-gray-500 dark:text-gray-400 shrink-0">{{ $row['count'] }} {{ $row['count'] === 1 ? 'factura' : 'facturas' }} · ${{ number_format($row['total'], 2) }}</span>
                            </div>
                            <div class="h-1.5 w-full rounded-full bg-gray-100 dark:bg-gray-800">
                                <div class="h-1.5 rounded-full bg-indigo-500 dark:bg-indigo-400" style="width: {{ $barPct($row['total'], $maxClient) }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400 dark:text-gray-500">Sin ventas en el período.</p>
                    @endforelse
                </div>
            </section>

            <section class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 p-5">
                <h2 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-4">Ventas por hora del día</h2>
                <div class="space-y-3">
                    @foreach ($byHour as $row)
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-700 dark:text-gray-300">{{ str_pad($row['hour'], 2, '0', STR_PAD_LEFT) }}:00 – {{ str_pad($row['hour'], 2, '0', STR_PAD_LEFT) }}:59</span>
                                <span class="text-gray-500 dark:text-gray-400">{{ $row['count'] }} {{ $row['count'] === 1 ? 'venta' : 'ventas' }} · ${{ number_format($row['total'], 2) }}</span>
                            </div>
                            <div class="h-1.5 w-full rounded-full bg-gray-100 dark:bg-gray-800">
                                <div class="h-1.5 rounded-full bg-indigo-500 dark:bg-indigo-400" style="width: {{ $barPct($row['total'], $maxHour) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>
    @endif
</div>
