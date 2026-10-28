<div class="p-8 max-w-6xl mx-auto">
    <x-page-header title="Pedidos online" subtitle="Pedidos hechos desde la tienda — pagos y retiros" icon="shopping-bag" />

    <div class="flex flex-col sm:flex-row gap-3 mb-5">
        <div class="relative flex-1 max-w-sm">
            <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" />
            <input
                type="text"
                wire:model.live.debounce.300ms="query"
                placeholder="Buscar por cliente o email..."
                class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder-gray-500 pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent hover:border-gray-400 dark:hover:border-gray-600 transition-colors"
            >
        </div>
        <div class="flex gap-1.5 flex-wrap">
            <button wire:click="$set('filterPago', 'all')" class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors {{ $filterPago === 'all' ? 'bg-indigo-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800' }}">
                Todos
            </button>
            <button wire:click="$set('filterPago', 'pending_payment')" class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors {{ $filterPago === 'pending_payment' ? 'bg-indigo-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800' }}">
                Pendiente de pago
            </button>
            <button wire:click="$set('filterPago', 'paid')" class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors {{ $filterPago === 'paid' ? 'bg-indigo-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800' }}">
                Pagado
            </button>
        </div>
        <div class="flex gap-1.5 flex-wrap">
            <button wire:click="$set('filterRetiro', 'all')" class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors {{ $filterRetiro === 'all' ? 'bg-sky-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800' }}">
                Cualquier retiro
            </button>
            <button wire:click="$set('filterRetiro', 'pendiente')" class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors {{ $filterRetiro === 'pendiente' ? 'bg-sky-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800' }}">
                Sin retirar
            </button>
            <button wire:click="$set('filterRetiro', 'retirado')" class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors {{ $filterRetiro === 'retirado' ? 'bg-sky-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800' }}">
                Retirado
            </button>
        </div>
    </div>

    @if ($pedidos->isEmpty())
        <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-12 text-center">
            <x-heroicon-o-shopping-bag class="w-10 h-10 mx-auto mb-3 text-gray-300 dark:text-gray-700" />
            <p class="text-sm text-gray-500 dark:text-gray-400">No se encontraron pedidos.</p>
        </div>
    @else
        <div class="hidden sm:block overflow-x-auto rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-800 bg-gray-100/80 dark:bg-gray-800/40">
                        <th class="px-5 py-3 font-medium">Cliente</th>
                        <th class="px-5 py-3 font-medium">Fecha</th>
                        <th class="px-5 py-3 font-medium">Pago</th>
                        <th class="px-5 py-3 font-medium">Retiro</th>
                        <th class="px-5 py-3 font-medium text-right">Total</th>
                        <th class="px-5 py-3 font-medium text-right">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pedidos as $pedido)
                        @php $documento = $pedido->invoice?->facturaGenerada() ?? $pedido->invoice; @endphp
                        <tr wire:key="pedido-{{ $pedido->id }}" class="border-b border-gray-50 dark:border-gray-800/60 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                            <td class="px-5 py-3">
                                <p class="font-medium text-gray-900 dark:text-gray-100">{{ $pedido->customer_name }}</p>
                                <p class="text-xs text-gray-400 dark:text-gray-500">{{ $pedido->customer_email }}</p>
                            </td>
                            <td class="px-5 py-3 text-gray-500 dark:text-gray-400">{{ $pedido->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-5 py-3">
                                @if ($pedido->status === 'paid')
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 px-2.5 py-1 text-xs font-semibold">Pagado</span>
                                @elseif ($pedido->status === 'pending_payment')
                                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 px-2.5 py-1 text-xs font-semibold">Pendiente de pago</span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 px-2.5 py-1 text-xs font-semibold">{{ ucfirst($pedido->status) }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                @if (! $pedido->invoice)
                                    <span class="text-gray-300 dark:text-gray-700">—</span>
                                @elseif ($pedido->estaRetirado())
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 px-2.5 py-1 text-xs font-semibold">Retirado</span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 px-2.5 py-1 text-xs font-semibold">Sin retirar</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right font-medium text-gray-900 dark:text-gray-100">
                                ${{ money($documento->total ?? 0) }}
                            </td>
                            <td class="px-5 py-3 text-right">
                                @if ($documento)
                                    <a href="{{ route('invoices.show', $documento) }}" wire:navigate class="text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium">
                                        Ver →
                                    </a>
                                @else
                                    <span class="text-gray-300 dark:text-gray-700">Vencido</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="sm:hidden space-y-3">
            @foreach ($pedidos as $pedido)
                @php $documento = $pedido->invoice?->facturaGenerada() ?? $pedido->invoice; @endphp
                <div wire:key="pedido-card-{{ $pedido->id }}" class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
                    <div class="flex items-start justify-between gap-3 mb-2">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $pedido->customer_name }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500">{{ $pedido->customer_email }}</p>
                        </div>
                        <p class="font-semibold text-gray-900 dark:text-gray-100 shrink-0">${{ money($documento->total ?? 0) }}</p>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap mb-2">
                        @if ($pedido->status === 'paid')
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 px-2.5 py-1 text-xs font-semibold">Pagado</span>
                        @elseif ($pedido->status === 'pending_payment')
                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 px-2.5 py-1 text-xs font-semibold">Pendiente de pago</span>
                        @else
                            <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 px-2.5 py-1 text-xs font-semibold">{{ ucfirst($pedido->status) }}</span>
                        @endif
                        @if ($pedido->invoice && $pedido->estaRetirado())
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 px-2.5 py-1 text-xs font-semibold">Retirado</span>
                        @elseif ($pedido->invoice)
                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 px-2.5 py-1 text-xs font-semibold">Sin retirar</span>
                        @endif
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <p class="text-gray-500 dark:text-gray-400">{{ $pedido->created_at->format('d/m/Y H:i') }}</p>
                        @if ($documento)
                            <a href="{{ route('invoices.show', $documento) }}" wire:navigate class="text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium">Ver →</a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $pedidos->links() }}
        </div>
    @endif
</div>
