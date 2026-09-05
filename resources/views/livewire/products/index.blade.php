<div class="p-8 max-w-5xl mx-auto">
    <x-page-header title="Productos" subtitle="Gestioná tu catálogo de productos" icon="cube">
        <x-slot:actions>
            <a href="{{ route('products.export') }}" class="inline-flex items-center gap-2 rounded-lg bg-white/15 border border-white/25 px-4 py-2 text-sm font-medium text-white hover:bg-white/25 active:scale-[0.98] transition-all">
                <x-heroicon-o-arrow-down-tray class="w-4 h-4" /> Exportar
            </a>
            <a href="{{ route('products.import') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg bg-white/15 border border-white/25 px-4 py-2 text-sm font-medium text-white hover:bg-white/25 active:scale-[0.98] transition-all">
                <x-heroicon-o-arrow-up-tray class="w-4 h-4" /> Importar
            </a>
            <a href="{{ route('products.labels') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg bg-white/15 border border-white/25 px-4 py-2 text-sm font-medium text-white hover:bg-white/25 active:scale-[0.98] transition-all">
                <x-heroicon-o-printer class="w-4 h-4" /> Etiquetas
            </a>
            <a href="{{ route('products.create') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-emerald-900/20 hover:bg-emerald-400 active:scale-[0.98] transition-all">
                <x-heroicon-o-plus class="w-4 h-4" /> Nuevo producto
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="flex items-center gap-3 mb-4">
        <div class="relative flex-1">
            <x-heroicon-o-magnifying-glass class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input
                type="text"
                wire:model.live.debounce.300ms="query"
                placeholder="Buscar por nombre, SKU o descripción..."
                class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder-gray-500 pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent hover:border-gray-400 dark:hover:border-gray-600 transition-colors"
            >
        </div>
        <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 shrink-0 select-none cursor-pointer">
            <input type="checkbox" wire:model.live="onlyAlerts" class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 focus:ring-indigo-500 dark:bg-gray-900">
            Solo con alertas
        </label>
    </div>

    <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40">
        @if (! $hasAnyProducts)
            <div class="p-12 text-center text-gray-400 dark:text-gray-500">
                <x-heroicon-o-cube class="w-10 h-10 mx-auto mb-3 text-gray-300 dark:text-gray-700" />
                <p class="text-sm">Todavía no agregaste productos.</p>
                <a href="{{ route('products.create') }}" wire:navigate class="text-sm text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300">
                    Agregar el primero
                </a>
            </div>
        @elseif ($products->isEmpty())
            <div class="p-12 text-center text-gray-400 dark:text-gray-500">
                <x-heroicon-o-magnifying-glass class="w-10 h-10 mx-auto mb-3 text-gray-300 dark:text-gray-700" />
                @if ($onlyAlerts && $query === '')
                    <p class="text-sm">Ningún producto tiene el stock por debajo del mínimo.</p>
                @elseif ($onlyAlerts)
                    <p class="text-sm">Sin resultados en alerta para "{{ $query }}".</p>
                @else
                    <p class="text-sm">Sin resultados para "{{ $query }}".</p>
                @endif
            </div>
        @else
            <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-800 bg-gray-100/80 dark:bg-gray-800/40">
                        <th class="px-5 py-3 font-medium">Nombre</th>
                        <th class="px-5 py-3 font-medium">SKU</th>
                        <th class="px-5 py-3 font-medium">Categoría</th>
                        <th class="px-5 py-3 font-medium">Precio</th>
                        <th class="px-5 py-3 font-medium">Stock</th>
                        <th class="px-5 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($products as $product)
                        @php
                            $alerts = collect([
                                $product->margin_alert ? 'Precio de venta por debajo del precio de compra' : null,
                                $product->stock_alert ? 'Stock por debajo del mínimo' : null,
                            ])->filter();
                        @endphp
                        <tr wire:key="product-{{ $product->id }}" class="border-b border-gray-50 dark:border-gray-800/60 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors {{ $alerts->isNotEmpty() ? 'bg-red-50/70 dark:bg-red-500/10' : '' }}">
                            <td class="px-5 py-3 font-medium text-gray-900 dark:text-gray-100">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-9 h-9 shrink-0 rounded-md overflow-hidden bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                                        @if ($product->imageUrl())
                                            <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="w-full h-full object-cover" loading="lazy">
                                        @else
                                            <x-heroicon-o-cube class="w-4 h-4 text-gray-300 dark:text-gray-600" />
                                        @endif
                                    </div>
                                    @if ($alerts->isNotEmpty())
                                        <span title="{{ $alerts->implode(' · ') }}">
                                            <x-heroicon-o-exclamation-triangle class="w-4 h-4 shrink-0 text-red-500 dark:text-red-400" />
                                        </span>
                                    @endif
                                    {{ $product->name }}
                                </div>
                            </td>
                            <td class="px-5 py-3 text-gray-500 dark:text-gray-400">{{ $product->sku ?: '—' }}</td>
                            <td class="px-5 py-3 text-gray-500 dark:text-gray-400">{{ $product->category?->name ?? '—' }}</td>
                            <td class="px-5 py-3 {{ $product->margin_alert ? 'font-medium text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-400' }}">
                                ${{ number_format($product->price, 2) }}
                            </td>
                            <td class="px-5 py-3 {{ $product->stock_alert ? 'font-medium text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-400' }}">
                                {{ $product->stock }}
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex justify-end gap-2">
                                    <a
                                        href="{{ route('products.historial', $product) }}"
                                        wire:navigate
                                        title="Ver historial"
                                        class="p-1.5 rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200 hover:scale-110 transition-all"
                                    >
                                        <x-heroicon-o-clock class="w-4 h-4" />
                                    </a>
                                    <a
                                        href="{{ route('products.edit', $product) }}"
                                        wire:navigate
                                        class="p-1.5 rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200 hover:scale-110 transition-all"
                                    >
                                        <x-heroicon-o-pencil class="w-4 h-4" />
                                    </a>
                                    <button
                                        x-on:click="confirmThen('¿Eliminar el producto ' + @js($product->name) + '?', () => $wire.delete({{ $product->id }}))"
                                        class="p-1.5 rounded-md text-gray-500 hover:bg-red-50 hover:text-red-600 dark:text-gray-400 dark:hover:bg-red-500/10 dark:hover:text-red-400 hover:scale-110 transition-all"
                                    >
                                        <x-heroicon-o-trash class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>

            <div class="sm:hidden divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($products as $product)
                    @php
                        $alerts = collect([
                            $product->margin_alert ? 'Precio de venta por debajo del precio de compra' : null,
                            $product->stock_alert ? 'Stock por debajo del mínimo' : null,
                        ])->filter();
                    @endphp
                    <div wire:key="product-card-{{ $product->id }}" class="p-4 {{ $alerts->isNotEmpty() ? 'bg-red-50/70 dark:bg-red-500/10' : '' }}">
                        <div class="flex items-start justify-between gap-3 mb-1.5">
                            <div class="min-w-0 flex items-center gap-1.5">
                                @if ($alerts->isNotEmpty())
                                    <span title="{{ $alerts->implode(' · ') }}">
                                        <x-heroicon-o-exclamation-triangle class="w-4 h-4 shrink-0 text-red-500 dark:text-red-400" />
                                    </span>
                                @endif
                                <p class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ $product->name }}</p>
                            </div>
                            <div class="flex gap-1 shrink-0">
                                <a
                                    href="{{ route('products.historial', $product) }}"
                                    wire:navigate
                                    title="Ver historial"
                                    class="p-1.5 rounded-md text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
                                >
                                    <x-heroicon-o-clock class="w-4 h-4" />
                                </a>
                                <a
                                    href="{{ route('products.edit', $product) }}"
                                    wire:navigate
                                    class="p-1.5 rounded-md text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
                                >
                                    <x-heroicon-o-pencil class="w-4 h-4" />
                                </a>
                                <button
                                    x-on:click="confirmThen('¿Eliminar el producto ' + @js($product->name) + '?', () => $wire.delete({{ $product->id }}))"
                                    class="p-1.5 rounded-md text-gray-500 hover:bg-red-50 hover:text-red-600 dark:text-gray-400 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                                >
                                    <x-heroicon-o-trash class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ $product->sku ?: '—' }} · {{ $product->category?->name ?? '—' }}</p>
                        <div class="flex items-center justify-between text-sm">
                            <span class="{{ $product->margin_alert ? 'font-medium text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-400' }}">
                                ${{ number_format($product->price, 2) }}
                            </span>
                            <span class="{{ $product->stock_alert ? 'font-medium text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-400' }}">
                                Stock: {{ $product->stock }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    @if ($hasAnyProducts && $products->isNotEmpty())
        <div class="mt-4">
            {{ $products->links() }}
        </div>
    @endif
</div>
