@props([
    'products',
    'addMethod' => 'addProduct',
    'emptyText' => 'No hay productos.',
])

{{-- Mobile: grilla de cards táctiles. Desktop: lista compacta. --}}

{{-- CARDS (solo mobile) --}}
<div class="grid grid-cols-2 sm:grid-cols-3 gap-2 lg:hidden">
    @forelse ($products as $product)
        <button
            type="button"
            wire:click="{{ $addMethod }}({{ $product->id }})"
            wire:key="pcard-{{ $product->id }}"
            class="group relative text-left rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-3 overflow-hidden hover:border-indigo-400 dark:hover:border-indigo-500 hover:shadow-lg hover:shadow-indigo-500/20 active:scale-[0.98] transition-all"
        >
            <span class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-indigo-500 via-violet-500 to-fuchsia-500 opacity-70"></span>
            <div class="w-full aspect-square mb-2 rounded-xl overflow-hidden bg-gradient-to-br from-indigo-50 to-violet-50 dark:from-gray-800 dark:to-gray-800/60 flex items-center justify-center">
                @if ($product->imageUrl())
                    <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="w-full h-full object-cover" loading="lazy">
                @else
                    <x-heroicon-o-cube class="w-8 h-8 text-indigo-300 dark:text-gray-600" />
                @endif
            </div>
            <p class="text-sm font-medium text-gray-900 dark:text-gray-100 leading-tight line-clamp-2 min-h-[2.5rem]">{{ $product->name }}</p>
            <div class="flex items-center justify-between mt-1.5">
                <span class="text-xs text-gray-400 dark:text-gray-500 truncate">{{ $product->sku ?: '—' }}</span>
                <span class="shrink-0 inline-flex items-center rounded-full bg-gradient-to-r from-indigo-600 to-violet-600 px-2.5 py-1 text-xs font-bold text-white shadow-sm">${{ number_format($product->price, 2) }}</span>
            </div>
        </button>
    @empty
        <p class="col-span-full text-sm text-gray-400 dark:text-gray-500 py-8 text-center">{{ $emptyText }}</p>
    @endforelse
</div>

{{-- LISTA (solo desktop) --}}
<div class="hidden lg:block rounded-xl border border-gray-200 dark:border-gray-800 divide-y divide-gray-100 dark:divide-gray-800 overflow-hidden">
    @forelse ($products as $product)
        <button
            type="button"
            wire:click="{{ $addMethod }}({{ $product->id }})"
            wire:key="prow-{{ $product->id }}"
            class="group w-full flex items-center gap-3 px-3 py-2 text-left bg-white dark:bg-gray-900 hover:bg-indigo-50/70 dark:hover:bg-indigo-500/10 transition-colors"
        >
            <span class="shrink-0 w-10 h-10 rounded-lg overflow-hidden bg-gradient-to-br from-indigo-50 to-violet-50 dark:from-gray-800 dark:to-gray-800/60 flex items-center justify-center">
                @if ($product->imageUrl())
                    <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="w-full h-full object-cover" loading="lazy">
                @else
                    <x-heroicon-o-cube class="w-5 h-5 text-indigo-300 dark:text-gray-600" />
                @endif
            </span>
            <span class="flex-1 min-w-0">
                <span class="block text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $product->name }}</span>
                <span class="block text-xs text-gray-400 dark:text-gray-500 truncate">{{ $product->sku ?: '—' }}</span>
            </span>
            <span class="shrink-0 inline-flex items-center rounded-full bg-gradient-to-r from-indigo-600 to-violet-600 px-2.5 py-1 text-xs font-bold text-white shadow-sm">${{ number_format($product->price, 2) }}</span>
            <x-heroicon-o-plus-circle class="shrink-0 w-5 h-5 text-gray-300 group-hover:text-indigo-500 dark:text-gray-600 dark:group-hover:text-indigo-400 transition-colors" />
        </button>
    @empty
        <p class="text-sm text-gray-400 dark:text-gray-500 py-8 text-center">{{ $emptyText }}</p>
    @endforelse
</div>
