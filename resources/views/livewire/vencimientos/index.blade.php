<div class="p-8 max-w-6xl mx-auto">
    <x-page-header title="Vencimientos" subtitle="Lo que te deben (por cobrar) y lo que debés a proveedores (por pagar), ordenado por fecha." icon="calendar-days" />

    @php
        $estadoChip = [
            'vencido' => 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-400',
            'hoy' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400',
            'proximo' => 'bg-gray-100 text-gray-600 dark:bg-gray-700/40 dark:text-gray-400',
        ];
        $etiquetaDias = function ($estado, $dias) {
            if ($estado === 'vencido') return 'Vencido hace '.abs($dias).' d';
            if ($estado === 'hoy') return 'Vence hoy';
            return 'En '.$dias.' d';
        };
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        {{-- POR COBRAR --}}
        <section class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-800 bg-emerald-50/70 dark:bg-emerald-500/10">
                <h2 class="text-sm font-semibold text-emerald-800 dark:text-emerald-300 inline-flex items-center gap-2">
                    <x-heroicon-o-arrow-down-left class="w-4 h-4" /> Por cobrar (clientes)
                </h2>
                <div class="text-right">
                    <p class="text-lg font-bold text-emerald-700 dark:text-emerald-400">${{ number_format($totalCobrar, 2) }}</p>
                    @if ($vencidoCobrar > 0.009)
                        <p class="text-[11px] text-red-600 dark:text-red-400">${{ number_format($vencidoCobrar, 2) }} vencido</p>
                    @endif
                </div>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-gray-800 max-h-[32rem] overflow-y-auto">
                @forelse ($porCobrar as $row)
                    <a href="{{ $row['href'] }}" wire:navigate class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $row['name'] }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500">{{ $row['label'] }} · vence {{ $row['due']->format('d/m/Y') }}</p>
                        </div>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium {{ $estadoChip[$row['estado']] }}">{{ $etiquetaDias($row['estado'], $row['dias']) }}</span>
                        <span class="w-24 text-right text-sm font-semibold text-gray-900 dark:text-gray-100 shrink-0">${{ number_format($row['amount'], 2) }}</span>
                    </a>
                @empty
                    <div class="px-4 py-10 text-center text-sm text-gray-400 dark:text-gray-500">Nadie te debe. ¡Todo cobrado! 🎉</div>
                @endforelse
            </div>
        </section>

        {{-- POR PAGAR --}}
        <section class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-800 bg-indigo-50/70 dark:bg-indigo-500/10">
                <h2 class="text-sm font-semibold text-indigo-800 dark:text-indigo-300 inline-flex items-center gap-2">
                    <x-heroicon-o-arrow-up-right class="w-4 h-4" /> Por pagar (proveedores)
                </h2>
                <div class="text-right">
                    <p class="text-lg font-bold text-indigo-700 dark:text-indigo-400">${{ number_format($totalPagar, 2) }}</p>
                    @if ($vencidoPagar > 0.009)
                        <p class="text-[11px] text-red-600 dark:text-red-400">${{ number_format($vencidoPagar, 2) }} vencido</p>
                    @endif
                </div>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-gray-800 max-h-[32rem] overflow-y-auto">
                @forelse ($porPagar as $row)
                    <a href="{{ $row['href'] }}" wire:navigate class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $row['name'] }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500">{{ $row['label'] }} · vence {{ $row['due']->format('d/m/Y') }}</p>
                        </div>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium {{ $estadoChip[$row['estado']] }}">{{ $etiquetaDias($row['estado'], $row['dias']) }}</span>
                        <span class="w-24 text-right text-sm font-semibold text-gray-900 dark:text-gray-100 shrink-0">${{ number_format($row['amount'], 2) }}</span>
                    </a>
                @empty
                    <div class="px-4 py-10 text-center text-sm text-gray-400 dark:text-gray-500">No le debés nada a nadie. 👍</div>
                @endforelse
            </div>
        </section>
    </div>
</div>
