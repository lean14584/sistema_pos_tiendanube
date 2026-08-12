<div class="p-8 max-w-6xl mx-auto">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Dashboard</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Resumen de tu actividad de facturación</p>
        </div>
        @if ($canManageInvoices)
            <a href="{{ route('invoices.create') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-indigo-600 to-indigo-500 px-4 py-2 text-sm font-medium text-white shadow-md shadow-indigo-600/30 hover:from-indigo-700 hover:to-indigo-600 hover:shadow-lg hover:shadow-indigo-600/40 active:scale-[0.98] transition-all">
                <x-heroicon-o-plus class="w-4 h-4" />
                Nueva factura
            </a>
        @endif
    </div>

    @if ($canManageInvoices && $pendientesEmisionCount > 0)
        <a href="{{ route('invoices.index') }}" wire:navigate
            class="flex items-center gap-3 rounded-xl border border-amber-300 bg-amber-50 dark:border-amber-500/30 dark:bg-amber-500/10 px-4 py-3 mb-6 hover:bg-amber-100 dark:hover:bg-amber-500/20 transition-colors">
            <x-heroicon-o-exclamation-triangle class="w-6 h-6 shrink-0 text-amber-600 dark:text-amber-400" />
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">
                    {{ $pendientesEmisionCount }} {{ $pendientesEmisionCount === 1 ? 'factura sin emitir a ARCA' : 'facturas sin emitir a ARCA' }}
                </p>
                <p class="text-xs text-amber-700/80 dark:text-amber-400/80">
                    Emitilas para que obtengan el CAE y entren al Libro IVA. Tocá para verlas.
                </p>
            </div>
            <x-heroicon-o-chevron-right class="w-5 h-5 shrink-0 text-amber-500" />
        </a>
    @endif

    @if ($canSeeHealth && $systemWarnings > 0)
        <a href="{{ route('health.index') }}" wire:navigate
            class="flex items-center gap-3 rounded-xl border border-amber-300 bg-amber-50 dark:border-amber-500/30 dark:bg-amber-500/10 px-4 py-3 mb-6 hover:bg-amber-100 dark:hover:bg-amber-500/20 transition-colors">
            <x-heroicon-o-heart class="w-6 h-6 shrink-0 text-amber-600 dark:text-amber-400" />
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">
                    {{ $systemWarnings }} {{ $systemWarnings === 1 ? 'aviso en el estado del sistema' : 'avisos en el estado del sistema' }}
                </p>
                <p class="text-xs text-amber-700/80 dark:text-amber-400/80">Respaldo, certificado ARCA, stock… Tocá para revisarlos.</p>
            </div>
            <x-heroicon-o-chevron-right class="w-5 h-5 shrink-0 text-amber-500" />
        </a>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
        <x-stat-card label="Total facturado (pagado)" value="${{ number_format($stats['totalRevenue'], 2) }}" icon="currency-dollar" color="emerald" />
        <x-stat-card label="Pendiente de cobro" value="${{ number_format($stats['pendingAmount'], 2) }}" icon="clock" color="amber" />
        <x-stat-card label="Facturas vencidas" value="{{ $stats['overdueCount'] }}" icon="exclamation-triangle" color="red" />
        <x-stat-card label="Total de facturas" value="{{ $stats['totalInvoices'] }}" icon="document-text" color="sky" />
        <x-stat-card label="Alertas de stock" value="{{ $lowStockCount }}" icon="cube" color="indigo" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6 mb-6">
        <section class="lg:col-span-2 bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 p-5">
            <h2 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-4">Top 5 productos más vendidos</h2>
            @if ($topProducts->isEmpty())
                <p class="text-sm text-gray-400 dark:text-gray-500">Todavía no hay ventas registradas.</p>
            @else
                <div class="space-y-3">
                    @foreach ($topProducts as $row)
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-700 dark:text-gray-300 truncate pr-2">{{ $row['label'] }}</span>
                                <span class="text-gray-500 dark:text-gray-400 shrink-0">${{ number_format($row['total'], 2) }}</span>
                            </div>
                            <div class="h-1.5 w-full rounded-full bg-gray-100 dark:bg-gray-800">
                                <div class="h-1.5 rounded-full bg-indigo-500 dark:bg-indigo-400" style="width: {{ $maxTopProduct > 0 ? max(2, round(($row['total'] / $maxTopProduct) * 100)) : 0 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="lg:col-span-3 bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-medium text-gray-900 dark:text-gray-100">Ventas por mes</h2>
                <select wire:model.live="year" class="rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    @foreach ($availableYears as $y)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endforeach
                </select>
            </div>

            @php
                $meses = [1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'];
            @endphp

            @if ($maxMonthlySales <= 0)
                <p class="text-sm text-gray-400 dark:text-gray-500">Sin ventas registradas en {{ $year }}.</p>
            @else
                <div class="flex items-end gap-2 h-36">
                    @foreach ($monthlySales as $mes => $total)
                        <div class="flex-1 h-full flex flex-col justify-end items-center" title="{{ $meses[$mes] }}: ${{ number_format($total, 2) }}">
                            <div class="w-full rounded-t-md bg-indigo-500 dark:bg-indigo-400 transition-all" style="height: {{ $total > 0 ? max(2, round(($total / $maxMonthlySales) * 100)) : 0 }}%"></div>
                        </div>
                    @endforeach
                </div>
                <div class="flex gap-2 mt-1.5">
                    @foreach ($meses as $label)
                        <span class="flex-1 text-center text-[10px] text-gray-400 dark:text-gray-500">{{ $label }}</span>
                    @endforeach
                </div>
            @endif
        </section>
    </div>

    <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40">
        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between">
            <h2 class="font-medium text-gray-900 dark:text-gray-100">Facturas recientes</h2>
            @if ($canManageInvoices)
                <a href="{{ route('invoices.index') }}" wire:navigate class="text-sm text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300">Ver todas</a>
            @endif
        </div>
        @if ($recentInvoices->isEmpty())
            <div class="p-10 text-center text-sm text-gray-400 dark:text-gray-500">
                Todavía no creaste ninguna factura.
                @if ($canManageInvoices)
                    <a href="{{ route('invoices.create') }}" wire:navigate class="text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300">Crear la primera</a>
                @endif
            </div>
        @else
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-800 bg-gray-100/80 dark:bg-gray-800/40">
                        <th class="px-5 py-3 font-medium">N°</th>
                        <th class="px-5 py-3 font-medium">Cliente</th>
                        <th class="px-5 py-3 font-medium">Vencimiento</th>
                        <th class="px-5 py-3 font-medium">Estado</th>
                        <th class="px-5 py-3 font-medium text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recentInvoices as $invoice)
                        <tr class="border-b border-gray-50 dark:border-gray-800/60 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                            <td class="px-5 py-3">
                                @if ($canManageInvoices)
                                    <a href="{{ route('invoices.show', $invoice) }}" wire:navigate class="text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium">{{ $invoice->number }}</a>
                                @else
                                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ $invoice->number }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-gray-700 dark:text-gray-300">{{ $invoice->client->name ?? 'Cliente desconocido' }}</td>
                            <td class="px-5 py-3 text-gray-500 dark:text-gray-400">{{ $invoice->due_date->format('d/m/Y') }}</td>
                            <td class="px-5 py-3"><x-status-badge :status="$invoice->effective_status" /></td>
                            <td class="px-5 py-3 text-right font-medium text-gray-900 dark:text-gray-100">${{ number_format($invoice->total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @endif
    </div>

    @if ($canManageProducts && $lowStockProducts->isNotEmpty())
        <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 mt-6">
            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between">
                <h2 class="font-medium text-gray-900 dark:text-gray-100">Productos con stock bajo</h2>
                <a href="{{ route('products.index', ['onlyAlerts' => 1]) }}" wire:navigate class="text-sm text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300">Ver todos</a>
            </div>
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-800 bg-gray-100/80 dark:bg-gray-800/40">
                        <th class="px-5 py-3 font-medium">Producto</th>
                        <th class="px-5 py-3 font-medium">Categoría</th>
                        <th class="px-5 py-3 font-medium text-right">Stock actual</th>
                        <th class="px-5 py-3 font-medium text-right">Mínimo</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lowStockProducts as $product)
                        <tr class="border-b border-gray-50 dark:border-gray-800/60 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                            <td class="px-5 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $product->name }}</td>
                            <td class="px-5 py-3 text-gray-500 dark:text-gray-400">{{ $product->category?->name ?? '—' }}</td>
                            <td class="px-5 py-3 text-right font-medium text-red-600 dark:text-red-400">{{ $product->stock }}</td>
                            <td class="px-5 py-3 text-right text-gray-500 dark:text-gray-400">{{ $product->min_stock }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>
    @endif
</div>
