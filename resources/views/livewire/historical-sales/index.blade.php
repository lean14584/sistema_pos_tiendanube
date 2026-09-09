<div class="p-8 max-w-5xl mx-auto">
    <x-page-header title="Ventas históricas" subtitle="Ventas importadas de otro sistema (ej. Tango), solo para consulta. No son facturas: no tienen numeración AFIP ni afectan la cuenta corriente." icon="clock">
        <x-slot:actions>
            <a href="{{ route('historical-sales.import') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg bg-white/15 border border-white/25 px-4 py-2 text-sm font-medium text-white hover:bg-white/25 active:scale-[0.98] transition-all">
                <x-heroicon-o-arrow-up-tray class="w-4 h-4" /> Importar Excel
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4">
        <input type="text" wire:model.live.debounce.400ms="buscar" placeholder="Buscar por cliente..." class="w-full sm:w-80 rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
    </div>

    <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40">
        @if ($ventas->isEmpty())
            <div class="p-12 text-center text-gray-400 dark:text-gray-500">
                <x-heroicon-o-clock class="w-10 h-10 mx-auto mb-3 text-gray-300 dark:text-gray-700" />
                <p class="text-sm">Todavía no importaste ventas históricas.</p>
            </div>
        @else
            <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-800 bg-gray-100/80 dark:bg-gray-800/40">
                        <th class="px-5 py-3 font-medium">Fecha</th>
                        <th class="px-5 py-3 font-medium">Cliente</th>
                        <th class="px-5 py-3 font-medium">Comprobante</th>
                        <th class="px-5 py-3 font-medium text-right">Total</th>
                        <th class="px-5 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($ventas as $venta)
                        <tr wire:key="venta-{{ $venta->id }}" class="border-b border-gray-50 dark:border-gray-800/60 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                            <td class="px-5 py-3 text-gray-500 dark:text-gray-400">{{ $venta->sale_date->format('d/m/Y') }}</td>
                            <td class="px-5 py-3 text-gray-700 dark:text-gray-300">
                                @if ($venta->client)
                                    <a href="{{ route('clients.account', $venta->client) }}" wire:navigate class="text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300">{{ $venta->client_name_raw }}</a>
                                @else
                                    {{ $venta->client_name_raw }}
                                @endif
                            </td>
                            <td class="px-5 py-3 text-gray-500 dark:text-gray-400">{{ trim(($venta->comprobante_type ?? '').' '.($venta->comprobante_number ?? '')) ?: '—' }}</td>
                            <td class="px-5 py-3 text-right text-gray-900 dark:text-gray-100">${{ money($venta->total) }}</td>
                            <td class="px-5 py-3 text-right">
                                <button
                                    x-on:click="confirmThen('¿Eliminar este registro histórico?', () => $wire.delete({{ $venta->id }}))"
                                    class="p-1.5 rounded-md text-gray-500 hover:bg-red-50 hover:text-red-600 dark:text-gray-400 dark:hover:bg-red-500/10 dark:hover:text-red-400 hover:scale-110 transition-all"
                                >
                                    <x-heroicon-o-trash class="w-4 h-4" />
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>

            <div class="sm:hidden divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($ventas as $venta)
                    <div wire:key="venta-card-{{ $venta->id }}" class="p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                @if ($venta->client)
                                    <a href="{{ route('clients.account', $venta->client) }}" wire:navigate class="font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300 truncate">{{ $venta->client_name_raw }}</a>
                                @else
                                    <p class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ $venta->client_name_raw }}</p>
                                @endif
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $venta->sale_date->format('d/m/Y') }} · {{ trim(($venta->comprobante_type ?? '').' '.($venta->comprobante_number ?? '')) ?: '—' }}</p>
                            </div>
                            <button
                                x-on:click="confirmThen('¿Eliminar este registro histórico?', () => $wire.delete({{ $venta->id }}))"
                                class="p-1.5 rounded-md text-gray-500 hover:bg-red-50 hover:text-red-600 dark:text-gray-400 dark:hover:bg-red-500/10 dark:hover:text-red-400 shrink-0"
                            >
                                <x-heroicon-o-trash class="w-4 h-4" />
                            </button>
                        </div>
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100 mt-1">${{ money($venta->total) }}</p>
                    </div>
                @endforeach
            </div>

            <div class="p-4 border-t border-gray-100 dark:border-gray-800">
                {{ $ventas->links() }}
            </div>
        @endif
    </div>
</div>
