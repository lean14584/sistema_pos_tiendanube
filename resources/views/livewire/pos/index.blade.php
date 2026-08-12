<div class="p-4 sm:p-6 max-w-7xl mx-auto">
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Venta rápida</h1>
        @if (session('status'))
            <span class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 px-3 py-1.5 text-sm font-medium">
                <x-heroicon-o-check-circle class="w-4 h-4" /> {{ session('status') }}
            </span>
        @endif
    </div>

    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-500/10 text-red-700 dark:text-red-400 px-4 py-3 text-sm">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {{-- Productos --}}
        <div class="lg:col-span-2 space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <div class="relative">
                    <x-heroicon-o-qr-code class="w-5 h-5 text-indigo-500 absolute left-3 top-1/2 -translate-y-1/2" />
                    <input
                        type="text"
                        autofocus
                        wire:model="barcode"
                        wire:keydown.enter.prevent="addByBarcode"
                        placeholder="Escaneá o escribí el código y Enter"
                        class="w-full rounded-lg border border-indigo-300 dark:border-indigo-700 dark:bg-gray-900 dark:text-gray-100 pl-10 pr-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                    >
                    @error('barcode') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="relative">
                    <x-heroicon-o-magnifying-glass class="w-5 h-5 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
                    <input
                        type="text"
                        wire:model.live.debounce.200ms="search"
                        placeholder="Buscar por nombre o SKU..."
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 pl-10 pr-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                    >
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-2">
                @forelse ($this->productos as $product)
                    <button
                        wire:click="addProduct({{ $product->id }})"
                        wire:key="prod-{{ $product->id }}"
                        class="text-left rounded-xl border border-gray-200 dark:border-gray-800 bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 p-3 hover:border-indigo-400 dark:hover:border-indigo-600 hover:shadow-md active:scale-[0.98] transition-all"
                    >
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100 leading-tight line-clamp-2 min-h-[2.5rem]">{{ $product->name }}</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ $product->sku ?: '—' }}</p>
                        <p class="text-base font-semibold text-indigo-600 dark:text-indigo-400 mt-1">${{ number_format($product->price, 2) }}</p>
                    </button>
                @empty
                    <p class="col-span-full text-sm text-gray-400 dark:text-gray-500 py-8 text-center">
                        {{ trim($search) !== '' ? 'Sin resultados.' : 'No hay productos cargados.' }}
                    </p>
                @endforelse
            </div>
        </div>

        {{-- Carrito --}}
        <div class="lg:sticky lg:top-4 self-start bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 flex flex-col max-h-[calc(100vh-7rem)]">
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-800">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Carrito ({{ $this->itemsCount() }})</h2>
                @if (count($cart) > 0)
                    <button wire:click="vaciar" class="text-xs text-gray-400 hover:text-red-600 dark:hover:text-red-400">Vaciar</button>
                @endif
            </div>

            <div class="flex-1 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($cart as $index => $item)
                    <div wire:key="cart-{{ $index }}" class="flex items-center gap-2 px-4 py-2.5">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $item['description'] }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500">${{ number_format($item['unit_price'], 2) }} · IVA {{ rtrim(rtrim($item['iva_rate'],'0'),'.') ?: '0' }}%</p>
                        </div>
                        <div class="flex items-center gap-1.5 shrink-0">
                            <button wire:click="dec({{ $index }})" class="w-7 h-7 rounded-md border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center justify-center">−</button>
                            <span class="w-6 text-center text-sm font-medium text-gray-900 dark:text-gray-100">{{ $item['quantity'] }}</span>
                            <button wire:click="inc({{ $index }})" class="w-7 h-7 rounded-md border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center justify-center">+</button>
                        </div>
                        <span class="w-20 text-right text-sm font-medium text-gray-900 dark:text-gray-100 shrink-0">
                            ${{ number_format($item['unit_price'] * $item['quantity'] * (1 + (float)$item['iva_rate']/100), 2) }}
                        </span>
                    </div>
                @empty
                    <div class="px-4 py-12 text-center text-sm text-gray-400 dark:text-gray-500">
                        <x-heroicon-o-shopping-cart class="w-8 h-8 mx-auto mb-2 text-gray-300 dark:text-gray-700" />
                        Escaneá o tocá un producto para empezar.
                    </div>
                @endforelse
            </div>

            @error('cart') <p class="px-4 pt-2 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

            <div class="border-t border-gray-100 dark:border-gray-800 p-4 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Total</span>
                    <span class="text-2xl font-bold text-gray-900 dark:text-gray-100">${{ number_format($this->total(), 2) }}</span>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <select wire:model="paymentMethod" class="rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        @foreach ($paymentMethods as $method)
                            <option value="{{ $method->value }}">{{ $method->label() }}</option>
                        @endforeach
                    </select>
                    <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 px-1">
                        <input type="checkbox" wire:model="printOnSale" class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 focus:ring-indigo-500">
                        Imprimir ticket
                    </label>
                </div>

                <button
                    wire:click="cobrar"
                    wire:loading.attr="disabled"
                    wire:target="cobrar"
                    @disabled(count($cart) === 0)
                    class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-emerald-600 to-emerald-500 px-4 py-3 text-base font-semibold text-white shadow-md shadow-emerald-600/30 hover:from-emerald-700 hover:to-emerald-600 active:scale-[0.99] disabled:opacity-50 disabled:cursor-not-allowed transition-all"
                >
                    <x-heroicon-o-banknotes class="w-5 h-5" />
                    <span wire:loading.remove wire:target="cobrar">Cobrar</span>
                    <span wire:loading wire:target="cobrar">Cobrando...</span>
                </button>
            </div>
        </div>
    </div>
</div>
