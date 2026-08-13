<div class="p-8 max-w-6xl mx-auto">
    <x-page-header title="Compras" subtitle="Todas tus compras a proveedores" icon="shopping-cart">
        <x-slot:actions>
            <a href="{{ route('purchases.suggestions') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg bg-white/15 border border-white/25 px-4 py-2 text-sm font-medium text-white hover:bg-white/25 transition-all">
                <x-heroicon-o-clipboard-document-check class="w-4 h-4" /> Sugerencias de compra
            </a>
            <a href="{{ route('purchases.create') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-semibold text-indigo-700 shadow-sm hover:bg-white/90 active:scale-[0.98] transition-all">
                <x-heroicon-o-plus class="w-4 h-4" /> Nueva compra
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="flex flex-col sm:flex-row gap-3 mb-5">
        <div class="relative flex-1 max-w-sm">
            <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" />
            <input
                type="text"
                wire:model.live.debounce.300ms="query"
                placeholder="Buscar por número o proveedor..."
                class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder-gray-500 pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent hover:border-gray-400 dark:hover:border-gray-600 transition-colors"
            >
        </div>
        <div class="flex gap-1.5 flex-wrap">
            <button wire:click="$set('filter', 'all')" class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors {{ $filter === 'all' ? 'bg-indigo-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800' }}">
                Todas
            </button>
            @foreach ($statuses as $s)
                <button wire:click="$set('filter', '{{ $s->value }}')" class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors {{ $filter === $s->value ? 'bg-indigo-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800' }}">
                    {{ $s->label() }}
                </button>
            @endforeach
        </div>
    </div>

    <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40">
        @if ($purchases->isEmpty())
            <div class="p-12 text-center text-gray-400 dark:text-gray-500">
                <x-heroicon-o-shopping-cart class="w-10 h-10 mx-auto mb-3 text-gray-300 dark:text-gray-700" />
                <p class="text-sm">No se encontraron compras.</p>
            </div>
        @else
            <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-800 bg-gray-100/80 dark:bg-gray-800/40">
                        <th class="px-5 py-3 font-medium">N°</th>
                        <th class="px-5 py-3 font-medium">Proveedor</th>
                        <th class="px-5 py-3 font-medium">Compra</th>
                        <th class="px-5 py-3 font-medium">Vencimiento</th>
                        <th class="px-5 py-3 font-medium">Estado</th>
                        <th class="px-5 py-3 font-medium text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($purchases as $purchase)
                        <tr wire:key="purchase-{{ $purchase->id }}" class="border-b border-gray-50 dark:border-gray-800/60 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                            <td class="px-5 py-3">
                                <a href="{{ route('purchases.show', $purchase) }}" wire:navigate class="text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium">
                                    {{ $purchase->number }}
                                </a>
                            </td>
                            <td class="px-5 py-3 text-gray-700 dark:text-gray-300">{{ $purchase->provider->name ?? 'Proveedor desconocido' }}</td>
                            <td class="px-5 py-3 text-gray-500 dark:text-gray-400">{{ $purchase->issue_date->format('d/m/Y') }}</td>
                            <td class="px-5 py-3 text-gray-500 dark:text-gray-400">{{ $purchase->due_date->format('d/m/Y') }}</td>
                            <td class="px-5 py-3"><x-status-badge :status="$purchase->effective_status" /></td>
                            <td class="px-5 py-3 text-right font-medium text-gray-900 dark:text-gray-100">${{ number_format($purchase->total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>

            <div class="sm:hidden divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($purchases as $purchase)
                    <a
                        href="{{ route('purchases.show', $purchase) }}"
                        wire:navigate
                        wire:key="purchase-card-{{ $purchase->id }}"
                        class="block p-4 active:bg-gray-50 dark:active:bg-gray-800/50 transition-colors"
                    >
                        <div class="flex items-start justify-between gap-3 mb-2">
                            <div class="min-w-0">
                                <p class="font-medium text-indigo-600 dark:text-indigo-400">{{ $purchase->number }}</p>
                                <p class="text-sm text-gray-700 dark:text-gray-300 truncate">{{ $purchase->provider->name ?? 'Proveedor desconocido' }}</p>
                            </div>
                            <x-status-badge :status="$purchase->effective_status" />
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <p class="text-gray-500 dark:text-gray-400">
                                {{ $purchase->issue_date->format('d/m/Y') }}
                                <span class="mx-1">·</span>
                                vence {{ $purchase->due_date->format('d/m/Y') }}
                            </p>
                            <p class="font-semibold text-gray-900 dark:text-gray-100">${{ number_format($purchase->total, 2) }}</p>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="p-4 border-t border-gray-100 dark:border-gray-800">
                {{ $purchases->links() }}
            </div>
        @endif
    </div>
</div>
