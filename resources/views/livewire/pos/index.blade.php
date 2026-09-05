<div class="p-4 sm:p-6 max-w-7xl mx-auto">
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-bold inline-flex items-center gap-2">
            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-sm">
                <x-heroicon-o-bolt class="w-5 h-5" />
            </span>
            <span class="bg-gradient-to-r from-indigo-600 to-violet-600 dark:from-indigo-400 dark:to-violet-400 bg-clip-text text-transparent">Venta rápida</span>
        </h1>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {{-- Lector + productos agregados --}}
        <div class="lg:col-span-2 space-y-3">
            <div class="relative">
                <div class="absolute left-4 top-1/2 -translate-y-1/2 flex items-center justify-center w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-sm">
                    <x-heroicon-o-qr-code class="w-5 h-5" />
                </div>
                <input
                    type="text"
                    autofocus
                    autocomplete="off"
                    wire:model.live.debounce.200ms="barcode"
                    wire:keydown.enter.prevent="addByBarcode"
                    placeholder="Escaneá el código de barras, o escribí para buscar por nombre/SKU"
                    class="w-full rounded-xl border-2 border-indigo-300 dark:border-indigo-700 dark:bg-gray-900 dark:text-gray-100 pl-14 pr-4 py-4 text-base focus:outline-none focus:ring-4 focus:ring-indigo-500/30 focus:border-indigo-500 shadow-sm"
                >
                @error('barcode') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror

                @if (trim($barcode) !== '')
                    <div class="absolute z-20 mt-1 w-full bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-lg max-h-72 overflow-y-auto">
                        @forelse ($this->barcodeResults as $product)
                            <button
                                type="button"
                                wire:click="selectFromBarcode({{ $product->id }})"
                                class="w-full flex items-center justify-between gap-3 px-4 py-2.5 text-left hover:bg-indigo-50/70 dark:hover:bg-indigo-500/10 border-b border-gray-50 dark:border-gray-800/60 last:border-0 transition-colors"
                            >
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $product->name }}</span>
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">
                                        Stock: {{ $product->stock }}{{ $product->sku ? " · SKU: {$product->sku}" : '' }} · ${{ money($product->priceForList($this->currentPriceList())) }}
                                    </span>
                                </span>
                            </button>
                        @empty
                            <p class="p-4 text-sm text-gray-400 dark:text-gray-500">Sin resultados para "{{ $barcode }}".</p>
                        @endforelse
                    </div>
                @endif
            </div>

            <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 overflow-hidden shadow-sm">
                <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-indigo-600 to-violet-600 text-white">
                    <h2 class="text-sm font-semibold inline-flex items-center gap-2">
                        <x-heroicon-o-shopping-cart class="w-4 h-4" /> Productos ({{ $this->itemsCount() }})
                    </h2>
                    @if (count($cart) > 0)
                        <button wire:click="vaciar" class="text-xs text-white/80 hover:text-white">Vaciar</button>
                    @endif
                </div>

                <div class="divide-y divide-gray-100 dark:divide-gray-800 min-h-[16rem] lg:min-h-[calc(100vh-16rem)]">
                    @forelse ($cart as $index => $item)
                        <div wire:key="cart-{{ $index }}" class="flex items-center gap-3 px-4 py-3 hover:bg-indigo-50/40 dark:hover:bg-indigo-500/5 transition-colors">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">
                                    {{ $item['description'] }}
                                    @php $promo = $this->promoLabel($item); @endphp
                                    @if ($promo)
                                        <span class="ml-1 inline-flex items-center gap-0.5 rounded-full bg-gradient-to-r from-emerald-500 to-teal-500 text-white px-2 py-0.5 text-[10px] font-bold align-middle shadow-sm">
                                            <x-heroicon-o-gift class="w-3 h-3" /> {{ $promo }}
                                        </span>
                                    @endif
                                </p>
                                <div class="flex items-center gap-1.5 text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                    <span>${{ money($item['unit_price']) }} c/u · IVA {{ rtrim(rtrim($item['iva_rate'],'0'),'.') ?: '0' }}%</span>
                                    <span class="text-gray-300 dark:text-gray-600">·</span>
                                    <span>Desc</span>
                                    <input type="number" min="0" max="100" step="0.01" wire:model.live="cart.{{ $index }}.discount" class="w-11 rounded border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-1 py-0.5 text-xs text-right focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                    <span>%</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-1 shrink-0 rounded-lg bg-gray-100 dark:bg-gray-800 p-0.5">
                                <button wire:click="dec({{ $index }})" class="w-8 h-8 rounded-md bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 shadow-sm flex items-center justify-center text-lg font-medium">−</button>
                                <span class="w-8 text-center text-sm font-bold text-gray-900 dark:text-gray-100">{{ $item['quantity'] }}</span>
                                <button wire:click="inc({{ $index }})" class="w-8 h-8 rounded-md bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 shadow-sm flex items-center justify-center text-lg font-medium">+</button>
                            </div>
                            <span class="w-24 text-right text-base font-bold text-gray-900 dark:text-gray-100 shrink-0">
                                ${{ money($this->lineTotal($item)) }}
                            </span>
                            <button wire:click="removeItem({{ $index }})" class="shrink-0 text-gray-300 hover:text-red-500 dark:text-gray-600 dark:hover:text-red-400">
                                <x-heroicon-o-x-mark class="w-5 h-5" />
                            </button>
                        </div>
                    @empty
                        <div class="flex flex-col items-center justify-center h-64 text-center text-gray-400 dark:text-gray-500 px-4">
                            <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-100 to-violet-100 dark:from-gray-800 dark:to-gray-800/50 flex items-center justify-center mb-3">
                                <x-heroicon-o-qr-code class="w-8 h-8 text-indigo-400 dark:text-gray-600" />
                            </div>
                            <p class="text-sm font-medium">Escaneá un producto para empezar</p>
                            <p class="text-xs mt-0.5">Pasá el código por el lector o escribilo y apretá Enter.</p>
                        </div>
                    @endforelse
                </div>
            </div>
            @error('cart') <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        {{-- Checkout --}}
        <div class="lg:sticky lg:top-4 self-start space-y-3">
            @php $posSelect = 'w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60 px-3 py-2.5 text-sm text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 focus:bg-white dark:focus:bg-gray-800 transition'; @endphp
            <div class="rounded-2xl border border-indigo-100 dark:border-gray-800 bg-gradient-to-b from-white to-indigo-50/60 dark:from-gray-900 dark:to-gray-950 shadow-md shadow-indigo-100/50 dark:shadow-black/30 p-4 space-y-3">
                {{-- Tipo de comprobante --}}
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Tipo de comprobante</label>
                    <select wire:model="tipo_comprobante_interno" class="{{ $posSelect }}">
                        @foreach ($tipoComprobanteInternoOptions as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Cliente y lista --}}
                <div>
                    <x-client-picker
                        :client-name="$clients->firstWhere('id', $client_id)?->name ?? '—'"
                        :client-query="$clientQuery"
                        :client-results="$this->clientResults"
                    />
                    @error('client_id') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Lista de precios</label>
                    <select wire:model.live="price_list_id" class="{{ $posSelect }}">
                        <option value="">Precio base</option>
                        @foreach ($priceLists as $list)
                            <option value="{{ $list->id }}">{{ $list->name }} ({{ (float) $list->adjustment_percent > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($list->adjustment_percent, 2), '0'), '.') }}%)</option>
                        @endforeach
                    </select>
                </div>

                {{-- Resumen con descuentos y promos --}}
                <div class="rounded-2xl overflow-hidden border border-indigo-100 dark:border-indigo-500/20 shadow-sm">
                    @if ($this->descuentosTotal() > 0.004)
                        <div class="bg-indigo-50/70 dark:bg-indigo-500/5 px-4 py-3 space-y-1.5">
                            <div class="flex items-center justify-between text-sm text-gray-500 dark:text-gray-400">
                                <span>Subtotal</span>
                                <span>${{ money($this->subtotalBruto()) }}</span>
                            </div>
                            @foreach ($this->promosAplicadas() as $promo)
                                <div class="flex items-center justify-between text-sm">
                                    <span class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400 font-medium">
                                        <x-heroicon-o-gift class="w-3.5 h-3.5" /> {{ $promo['label'] }}
                                    </span>
                                    <span class="text-emerald-600 dark:text-emerald-400 font-medium">−${{ money($promo['amount']) }}</span>
                                </div>
                            @endforeach
                            <div class="flex items-center justify-between text-sm font-semibold text-emerald-600 dark:text-emerald-400 pt-1 border-t border-indigo-100 dark:border-indigo-500/20">
                                <span>Descuento total</span>
                                <span>−${{ money($this->descuentosTotal()) }}</span>
                            </div>
                        </div>
                    @endif
                    <div class="relative bg-gradient-to-br from-indigo-600 to-violet-700 px-4 py-4 text-white overflow-hidden">
                        <div class="absolute -right-6 -top-8 w-28 h-28 rounded-full bg-white/10"></div>
                        <div class="absolute -right-2 bottom-2 opacity-20">
                            <x-heroicon-o-banknotes class="w-14 h-14" />
                        </div>
                        <div class="relative">
                            <div class="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-white/80">
                                <span>Total a cobrar</span>
                                <span class="rounded-full bg-white/20 px-1.5 py-0.5 text-[10px] normal-case">{{ $this->itemsCount() }} art.</span>
                            </div>
                            <div class="text-4xl font-extrabold tracking-tight mt-0.5">${{ money($this->total()) }}</div>
                        </div>
                    </div>
                </div>

                {{-- Medios de pago --}}
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Medios de pago</span>
                        <button type="button" wire:click="addPayment" class="inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 font-medium">
                            <x-heroicon-o-plus class="w-4 h-4" /> Agregar
                        </button>
                    </div>

                    @forelse ($payments as $index => $payment)
                        <div wire:key="pay-{{ $index }}" class="flex items-center gap-2">
                            <select wire:model="payments.{{ $index }}.method" class="flex-1 {{ $posSelect }}">
                                @foreach ($paymentMethods as $method)
                                    <option value="{{ $method->value }}">{{ $method->label() }}</option>
                                @endforeach
                            </select>
                            <input type="number" min="0" step="0.01" wire:model.live="payments.{{ $index }}.amount" class="w-28 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60 px-3 py-2.5 text-sm text-right text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white dark:focus:bg-gray-800 transition">
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
                            <span class="font-medium text-gray-900 dark:text-gray-100">${{ money($this->paymentsTotal()) }}</span>
                        </div>
                    @endif
                    @if ($this->saldoPendiente() > 0)
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-amber-600 dark:text-amber-400">Saldo a cuenta corriente</span>
                            <span class="font-semibold text-amber-600 dark:text-amber-400">${{ money($this->saldoPendiente()) }}</span>
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
                    class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-emerald-600 to-emerald-500 px-4 py-3.5 text-base font-semibold text-white shadow-lg shadow-emerald-600/30 hover:from-emerald-700 hover:to-emerald-600 active:scale-[0.99] disabled:opacity-50 disabled:cursor-not-allowed transition-all"
                >
                    <x-heroicon-o-banknotes class="w-5 h-5" />
                    <span wire:loading.remove wire:target="cobrar">Cobrar ${{ money($this->total()) }}</span>
                    <span wire:loading wire:target="cobrar">Cobrando...</span>
                </button>
            </div>
        </div>
    </div>
</div>
