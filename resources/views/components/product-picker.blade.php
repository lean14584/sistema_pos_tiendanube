@props(['productName' => '—', 'productQuery' => '', 'productResults' => null, 'label' => 'Producto *'])

{{--
    Card con el producto actual + F2/F3 abre un modal con el buscador. Asume
    que el componente Livewire padre tiene `productQuery` (propiedad),
    `productResults` (computed) y `selectProduct($id)` (acción) — mismo
    contrato que x-client-picker, adaptado a Product.
--}}
<div x-data="{ open: false }" x-on:keydown.f2.window.prevent="open = true" x-on:keydown.f3.window.prevent="open = true">
    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $label }}</label>
    <button
        type="button"
        x-on:click="open = true"
        class="w-full flex items-center justify-between gap-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/60 px-3 py-2.5 text-left text-sm text-gray-800 dark:text-gray-100 shadow-sm hover:border-indigo-400 dark:hover:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition"
    >
        <span class="truncate font-medium">{{ $productName }}</span>
        <span class="shrink-0 inline-flex items-center gap-1 text-xs text-gray-400 dark:text-gray-500">
            <kbd class="rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 px-1.5 py-0.5 font-sans text-[10px]">F2</kbd>
            <x-heroicon-o-magnifying-glass class="w-3.5 h-3.5" />
        </span>
    </button>

    <div
        x-show="open"
        x-cloak
        x-on:keydown.escape.window="open = false"
        class="fixed inset-0 z-50 flex items-start justify-center pt-24 px-4"
    >
        <div x-show="open" x-on:click="open = false" x-transition.opacity class="fixed inset-0 bg-gray-900/50"></div>

        <div
            x-show="open"
            x-on:click.stop
            x-transition
            x-init="$watch('open', value => { if (value) { $nextTick(() => $refs.productSearchInput.focus()) } })"
            class="relative w-full max-w-md bg-white dark:bg-gray-900 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-800 overflow-hidden"
        >
            <div class="p-3 border-b border-gray-100 dark:border-gray-800">
                <div class="relative">
                    <x-heroicon-o-magnifying-glass class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
                    <input
                        type="text"
                        x-ref="productSearchInput"
                        wire:model.live.debounce.200ms="productQuery"
                        placeholder="Buscar producto por nombre o SKU..."
                        class="w-full rounded-lg border border-gray-200 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 dark:placeholder-gray-500 pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                </div>
            </div>
            <div class="max-h-72 overflow-y-auto">
                @if (trim($productQuery) !== '')
                    @forelse ($productResults as $product)
                        <button
                            type="button"
                            wire:click="selectProduct({{ $product->id }})"
                            x-on:click="open = false"
                            class="w-full flex items-center justify-between gap-3 px-4 py-2.5 text-left hover:bg-indigo-50/70 dark:hover:bg-indigo-500/10 border-b border-gray-50 dark:border-gray-800/60 last:border-0 transition-colors"
                        >
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $product->name }}</span>
                                <span class="block text-xs text-gray-500 dark:text-gray-400">
                                    Stock: {{ $product->stock }}{{ $product->sku ? " · SKU: {$product->sku}" : '' }}
                                </span>
                            </span>
                        </button>
                    @empty
                        <p class="p-4 text-sm text-gray-400 dark:text-gray-500">Sin resultados para "{{ $productQuery }}".</p>
                    @endforelse
                @else
                    <p class="p-4 text-sm text-gray-400 dark:text-gray-500">Escribí para buscar, o apretá Escape para cerrar.</p>
                @endif
            </div>
        </div>
    </div>
</div>
