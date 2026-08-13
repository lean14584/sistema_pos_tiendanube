@php
    $numeroCompleto = fn ($row) => str_pad($row->puntoVenta, 4, '0', STR_PAD_LEFT).'-'.str_pad($row->numeroComprobante, 8, '0', STR_PAD_LEFT);
@endphp

<div class="p-8 max-w-6xl mx-auto">
    <x-page-header title="Libro IVA Digital" subtitle="Comprobantes de ventas y compras del período, según el diseño de registro de ARCA (ex AFIP, RG 4597)" icon="receipt-percent">
        <x-slot:actions>
            <a href="{{ route('libro-iva.export', ['desde' => $fromDate, 'hasta' => $toDate]) }}" class="inline-flex items-center gap-2 rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-emerald-900/20 hover:bg-emerald-400 active:scale-[0.98] transition-all">
                <x-heroicon-o-arrow-down-tray class="w-4 h-4" /> Exportar (.zip)
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

        <div class="sm:ml-auto flex rounded-lg border border-gray-200 dark:border-gray-800 p-1 bg-gray-50 dark:bg-gray-800/50">
            <button
                type="button"
                wire:click="$set('tab', 'ventas')"
                class="px-4 py-1.5 text-sm font-medium rounded-md transition-colors {{ $tab === 'ventas' ? 'bg-white dark:bg-gray-900 text-indigo-700 dark:text-indigo-400 shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200' }}"
            >
                Ventas
            </button>
            <button
                type="button"
                wire:click="$set('tab', 'compras')"
                class="px-4 py-1.5 text-sm font-medium rounded-md transition-colors {{ $tab === 'compras' ? 'bg-white dark:bg-gray-900 text-indigo-700 dark:text-indigo-400 shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200' }}"
            >
                Compras
            </button>
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <x-stat-card label="Neto gravado" value="${{ number_format($totalNeto, 2) }}" icon="banknotes" />
        <x-stat-card label="IVA liquidado" value="${{ number_format($totalIva, 2) }}" icon="receipt-percent" />
        <x-stat-card label="Exento" value="${{ number_format($totalExento, 2) }}" icon="no-symbol" accent="text-gray-600 bg-gradient-to-br from-gray-50 to-gray-100/60 dark:text-gray-400 dark:from-gray-500/15 dark:to-gray-500/5" />
        <x-stat-card label="Total" value="${{ number_format($totalGeneral, 2) }}" icon="calculator" accent="text-emerald-600 bg-gradient-to-br from-emerald-50 to-emerald-100/60 dark:text-emerald-400 dark:from-emerald-500/15 dark:to-emerald-500/5" />
    </div>

    @if ($resumen->isNotEmpty())
        <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 p-5 mb-6">
            <h2 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-4">Resumen por alícuota</h2>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                @foreach ($resumen as $item)
                    <div>
                        <p class="text-xs text-gray-400 dark:text-gray-500 uppercase">{{ rtrim(rtrim(number_format($item['tasa'], 2), '0'), '.') }}%</p>
                        <p class="font-semibold text-gray-900 dark:text-gray-100">${{ number_format($item['iva'], 2) }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">neto ${{ number_format($item['netoGravado'], 2) }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 overflow-hidden">
        @if ($rows->isEmpty())
            <div class="p-12 text-center text-gray-400 dark:text-gray-500">
                <x-heroicon-o-document-text class="w-10 h-10 mx-auto mb-3 text-gray-300 dark:text-gray-700" />
                <p class="text-sm">No hay comprobantes {{ $tab === 'compras' ? 'de compra' : 'fiscales' }} en el período seleccionado.</p>
            </div>
        @else
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800/50">
                    <tr class="text-left text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-2 font-medium">Fecha</th>
                        <th class="px-4 py-2 font-medium">Comprobante</th>
                        <th class="px-4 py-2 font-medium">{{ $tab === 'compras' ? 'Proveedor' : 'Cliente' }}</th>
                        <th class="px-4 py-2 font-medium text-right">Neto gravado</th>
                        <th class="px-4 py-2 font-medium text-right">Exento</th>
                        <th class="px-4 py-2 font-medium text-right">IVA</th>
                        <th class="px-4 py-2 font-medium text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="border-t border-gray-100 dark:border-gray-800">
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $row->fecha->format('d/m/Y') }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">
                                {{ $row->tipoComprobante->label() }}
                                <span class="text-gray-400 dark:text-gray-500">· {{ $numeroCompleto($row) }}</span>
                            </td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300 truncate max-w-[220px]">{{ $row->denominacion }}</td>
                            <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300">${{ number_format($row->importeNetoGravado, 2) }}</td>
                            <td class="px-4 py-2 text-right text-gray-500 dark:text-gray-400">${{ number_format($row->importeExento, 2) }}</td>
                            <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300">${{ number_format($row->ivaLiquidado, 2) }}</td>
                            <td class="px-4 py-2 text-right font-medium text-gray-900 dark:text-gray-100">${{ number_format($row->importeTotal, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @endif
    </div>
</div>
