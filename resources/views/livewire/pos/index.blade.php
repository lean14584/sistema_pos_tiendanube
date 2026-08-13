<div class="p-4 sm:p-6 max-w-7xl mx-auto">
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-bold inline-flex items-center gap-2">
            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-sm">
                <x-heroicon-o-bolt class="w-5 h-5" />
            </span>
            <span class="bg-gradient-to-r from-indigo-600 to-violet-600 dark:from-indigo-400 dark:to-violet-400 bg-clip-text text-transparent">Venta rápida</span>
        </h1>
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
                        class="group relative text-left rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-3 overflow-hidden hover:border-indigo-400 dark:hover:border-indigo-500 hover:shadow-lg hover:shadow-indigo-500/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all"
                    >
                        <span class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-indigo-500 via-violet-500 to-fuchsia-500 opacity-70 group-hover:opacity-100 transition-opacity"></span>
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
                    <p class="col-span-full text-sm text-gray-400 dark:text-gray-500 py-8 text-center">
                        {{ trim($search) !== '' ? 'Sin resultados.' : 'No hay productos cargados.' }}
                    </p>
                @endforelse
            </div>
        </div>

        {{-- Carrito --}}
        <div class="lg:sticky lg:top-4 self-start bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 flex flex-col max-h-[calc(100vh-7rem)]">
            <div class="flex items-center justify-between px-4 py-3 rounded-t-xl bg-gradient-to-r from-indigo-600 to-violet-600 text-white">
                <h2 class="text-sm font-semibold inline-flex items-center gap-2">
                    <x-heroicon-o-shopping-cart class="w-4 h-4" /> Carrito ({{ $this->itemsCount() }})
                </h2>
                @if (count($cart) > 0)
                    <button wire:click="vaciar" class="text-xs text-white/80 hover:text-white">Vaciar</button>
                @endif
            </div>

            <div class="flex-1 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($cart as $index => $item)
                    <div wire:key="cart-{{ $index }}" class="flex items-center gap-2 px-4 py-2.5">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                                {{ $item['description'] }}
                                @php $promo = $this->promoLabel($item); @endphp
                                @if ($promo)
                                    <span class="ml-1 inline-flex items-center rounded-full bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 px-1.5 py-0.5 text-[10px] font-semibold align-middle">{{ $promo }}</span>
                                @endif
                            </p>
                            <div class="flex items-center gap-1.5 text-xs text-gray-400 dark:text-gray-500">
                                <span>${{ number_format($item['unit_price'], 2) }} · IVA {{ rtrim(rtrim($item['iva_rate'],'0'),'.') ?: '0' }}%</span>
                                <span class="text-gray-300 dark:text-gray-600">·</span>
                                <span>Desc</span>
                                <input type="number" min="0" max="100" step="0.01" wire:model.live="cart.{{ $index }}.discount" class="w-12 rounded border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-1 py-0.5 text-xs text-right focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                <span>%</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5 shrink-0">
                            <button wire:click="dec({{ $index }})" class="w-7 h-7 rounded-md border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center justify-center">−</button>
                            <span class="w-6 text-center text-sm font-medium text-gray-900 dark:text-gray-100">{{ $item['quantity'] }}</span>
                            <button wire:click="inc({{ $index }})" class="w-7 h-7 rounded-md border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center justify-center">+</button>
                        </div>
                        <span class="w-20 text-right text-sm font-medium text-gray-900 dark:text-gray-100 shrink-0">
                            ${{ number_format($this->lineTotal($item), 2) }}
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
                {{-- Cliente y lista de precios --}}
                <div class="grid grid-cols-1 gap-2">
                    <div>
                        <select wire:model.live="client_id" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            @foreach ($clients as $client)
                                <option value="{{ $client->id }}">{{ $client->name }}</option>
                            @endforeach
                        </select>
                        @error('client_id') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-gray-500 dark:text-gray-400 shrink-0">Lista</span>
                        <select wire:model.live="price_list_id" class="flex-1 rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">Precio base</option>
                            @foreach ($priceLists as $list)
                                <option value="{{ $list->id }}">{{ $list->name }} ({{ (float) $list->adjustment_percent > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($list->adjustment_percent, 2), '0'), '.') }}%)</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Resumen con descuentos y promos --}}
                <div class="rounded-xl bg-gradient-to-br from-indigo-50 to-violet-50 dark:from-indigo-500/10 dark:to-violet-500/10 border border-indigo-100 dark:border-indigo-500/20 p-3 space-y-1.5">
                    @if ($this->descuentosTotal() > 0.004)
                        <div class="flex items-center justify-between text-sm text-gray-500 dark:text-gray-400">
                            <span>Subtotal</span>
                            <span>${{ number_format($this->subtotalBruto(), 2) }}</span>
                        </div>
                        @foreach ($this->promosAplicadas() as $promo)
                            <div class="flex items-center justify-between text-sm">
                                <span class="inline-flex items-center gap-1 text-emerald-700 dark:text-emerald-400 font-medium">
                                    <x-heroicon-o-gift class="w-3.5 h-3.5" /> {{ $promo['label'] }}
                                </span>
                                <span class="text-emerald-700 dark:text-emerald-400 font-medium">−${{ number_format($promo['amount'], 2) }}</span>
                            </div>
                        @endforeach
                        <div class="flex items-center justify-between text-sm font-medium text-emerald-700 dark:text-emerald-400 pb-1 border-b border-indigo-100 dark:border-indigo-500/20">
                            <span>Descuento total</span>
                            <span>−${{ number_format($this->descuentosTotal(), 2) }}</span>
                        </div>
                    @endif
                    <div class="flex items-end justify-between pt-0.5">
                        <span class="text-sm font-medium text-gray-600 dark:text-gray-300">Total</span>
                        <span class="text-3xl font-extrabold bg-gradient-to-r from-indigo-600 to-violet-600 dark:from-indigo-400 dark:to-violet-400 bg-clip-text text-transparent">${{ number_format($this->total(), 2) }}</span>
                    </div>
                </div>

                {{-- Medios de pago (uno o varios) --}}
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Medios de pago</span>
                        <button type="button" wire:click="addPayment" class="inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 font-medium">
                            <x-heroicon-o-plus class="w-4 h-4" /> Agregar
                        </button>
                    </div>

                    @forelse ($payments as $index => $payment)
                        <div wire:key="pay-{{ $index }}" class="flex items-center gap-2">
                            <select wire:model="payments.{{ $index }}.method" class="flex-1 rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-2 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                @foreach ($paymentMethods as $method)
                                    <option value="{{ $method->value }}">{{ $method->label() }}</option>
                                @endforeach
                            </select>
                            <input type="number" min="0" step="0.01" wire:model.live="payments.{{ $index }}.amount" class="w-28 rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-2 py-2 text-sm text-right focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <button type="button" wire:click="removePayment({{ $index }})" class="text-gray-400 hover:text-red-600 dark:hover:text-red-400 shrink-0">
                                <x-heroicon-o-x-mark class="w-5 h-5" />
                            </button>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400 dark:text-gray-500">Sin pago cargado: la venta queda como saldo en la cuenta corriente del cliente.</p>
                    @endforelse
                    @error('payments') <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                    @if (count($payments) > 0)
                        <div class="flex items-center justify-between text-sm pt-1">
                            <span class="text-gray-500 dark:text-gray-400">Pagado</span>
                            <span class="font-medium text-gray-900 dark:text-gray-100">${{ number_format($this->paymentsTotal(), 2) }}</span>
                        </div>
                    @endif
                    @if ($this->saldoPendiente() > 0)
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-amber-600 dark:text-amber-400">Saldo a cuenta corriente</span>
                            <span class="font-semibold text-amber-600 dark:text-amber-400">${{ number_format($this->saldoPendiente(), 2) }}</span>
                        </div>
                    @endif
                </div>

                <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 px-1">
                    <input type="checkbox" wire:model="printOnSale" class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 focus:ring-indigo-500">
                    Imprimir ticket
                </label>

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
