@php
    $inputClass = 'w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/60 dark:text-gray-100 px-3 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-400 focus:border-sky-400 transition';
    $sectionTitle = 'text-xs font-bold text-sky-700 dark:text-sky-400 uppercase tracking-wider';
    $sectionDivider = 'border-t border-sky-200/70 dark:border-gray-800 pt-5 mt-5';
    $label = 'block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1';
@endphp

<div class="p-4 sm:p-6 max-w-7xl mx-auto">
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-bold inline-flex items-center gap-2">
            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-gradient-to-br from-sky-500 to-sky-600 text-white shadow-sm">
                <x-heroicon-o-bolt class="w-5 h-5" />
            </span>
            <span class="text-gray-900 dark:text-gray-100">Venta rápida</span>
        </h1>
    </div>

    @if (! $this->hasOpenCashSession)
        <div class="mb-4 bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 rounded-xl p-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2 text-amber-800 dark:text-amber-300">
                <x-heroicon-o-exclamation-triangle class="w-5 h-5 shrink-0" />
                <p class="text-sm">Todavía no abriste la caja. Podés armar el carrito, pero no vas a poder cobrar hasta abrirla.</p>
            </div>
            <a href="{{ route('cash-register.index') }}" wire:navigate class="shrink-0 rounded-lg bg-amber-600 hover:bg-amber-700 px-3 py-1.5 text-xs font-medium text-white">
                Abrir caja
            </a>
        </div>
    @endif

    <div class="rounded-2xl border border-sky-200 dark:border-gray-800 bg-sky-200/70 dark:bg-gray-900 shadow-sm p-5 sm:p-7">
      <div class="grid grid-cols-1 lg:grid-cols-12 lg:gap-x-8">
        {{-- Columna izquierda: lector + productos agregados --}}
        <div class="lg:col-span-8">
        {{--
            Comprobante + Cliente (+ Lista de precios, si ese módulo está activo), discreto
            arriba del lector: no compite en jerarquía visual con la grilla de productos ni
            se mete en la columna de cobro. Con listas de precios activas (ej. pos-tiendanube)
            va en 3 columnas con Lista de precios aparte; sin ese módulo (ej. DECO-HOGAR/prueba)
            queda en 2 columnas, igual que antes.
        --}}
        <div class="rounded-xl bg-white/80 dark:bg-gray-800/40 border border-gray-200/80 dark:border-gray-700/60 p-4 mb-4 grid grid-cols-1 {{ config('features.price_lists') ? 'md:grid-cols-3' : 'md:grid-cols-2' }} gap-4 md:gap-8">
        <div class="space-y-3">
            <h3 class="{{ $sectionTitle }}">Comprobante</h3>

            <div>
                <select wire:model.live="tipo_comprobante_interno" class="{{ $inputClass }}">
                    @foreach ($tipoComprobanteInternoOptions as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </select>
            </div>

            @if ($tipo_comprobante_interno === 'devolucion')
                <div class="rounded-xl border border-amber-200 dark:border-amber-500/20 bg-amber-50/60 dark:bg-amber-500/5 p-3 space-y-2">
                    <p class="text-xs font-semibold text-amber-700 dark:text-amber-400 uppercase tracking-wide">Cambio / Devolución</p>

                    @if (! $facturaOrigen)
                        <div class="flex items-center gap-2">
                            <input
                                type="text"
                                wire:model="numeroFacturaOrigen"
                                wire:keydown.enter.prevent="buscarFacturaOrigen"
                                placeholder="Nº de factura/remito a devolver"
                                class="flex-1 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/60 px-3 py-2 text-sm dark:text-gray-100"
                            >
                            <button type="button" wire:click="buscarFacturaOrigen" class="shrink-0 rounded-lg bg-amber-600 hover:bg-amber-700 px-3 py-2 text-xs font-medium text-white">
                                Buscar
                            </button>
                        </div>
                        @error('numeroFacturaOrigen') <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                        @if ($facturaOrigenCandidatas->isNotEmpty())
                            <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach ($facturaOrigenCandidatas as $candidata)
                                    <button type="button" wire:key="candidata-{{ $candidata->id }}" wire:click="elegirFacturaOrigen({{ $candidata->id }})" class="w-full text-left px-3 py-2 text-sm hover:bg-amber-50 dark:hover:bg-amber-500/10">
                                        {{ $candidata->tipo_comprobante_interno->label() }} · {{ $candidata->issue_date->format('d/m/Y') }}
                                    </button>
                                @endforeach
                            </div>
                        @endif

                        <p class="text-xs text-amber-700/70 dark:text-amber-400/70">
                            Sin número: funciona como una devolución simple, sin producto nuevo ni vale.
                        </p>
                    @else
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-medium text-amber-800 dark:text-amber-300">
                                {{ $facturaOrigen->tipo_comprobante_interno->label() }} {{ $facturaOrigen->number }}
                            </span>
                            <button type="button" wire:click="quitarFacturaOrigen" aria-label="Quitar factura origen" class="text-gray-400 hover:text-red-600">
                                <x-heroicon-o-x-mark class="w-4 h-4" />
                            </button>
                        </div>

                        <div class="space-y-1">
                            @foreach ($itemsADevolver as $index => $item)
                                <div wire:key="devolver-{{ $index }}" class="flex items-center gap-2 text-xs">
                                    <span class="flex-1 truncate text-gray-700 dark:text-gray-300">{{ $item['description'] }}</span>
                                    <input type="number" step="0.01" min="0" wire:model.live="itemsADevolver.{{ $index }}.quantity" class="w-16 rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-1.5 py-1 text-right dark:text-gray-100">
                                    <span class="w-20 text-right text-gray-500 dark:text-gray-400">${{ money($item['unit_price']) }}</span>
                                    <button type="button" wire:click="removeItemADevolver({{ $index }})" aria-label="Quitar ítem a devolver" class="text-gray-300 hover:text-red-500 dark:text-gray-600">
                                        <x-heroicon-o-x-mark class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            @endforeach
                        </div>

                        <div class="flex items-center justify-between text-sm font-semibold text-amber-700 dark:text-amber-400 pt-1 border-t border-amber-200 dark:border-amber-500/20">
                            <span>Crédito por devolución</span>
                            <span>${{ money($this->creditoTotal()) }}</span>
                        </div>

                        @if (count($cart) > 0)
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-500 dark:text-gray-400">{{ $this->diferencia() >= 0 ? 'Diferencia a cobrar' : 'Sobrante a favor (vale)' }}</span>
                                <span class="font-semibold {{ $this->diferencia() >= 0 ? 'text-gray-900 dark:text-gray-100' : 'text-emerald-600 dark:text-emerald-400' }}">
                                    ${{ money(abs($this->diferencia())) }}
                                </span>
                            </div>
                        @else
                            <p class="text-xs text-amber-700/80 dark:text-amber-400/80">
                                Sin producto nuevo: se emite un vale por el total devuelto.
                            </p>
                        @endif
                    @endif
                </div>
            @endif

            @if ($this->puntosVentaOpciones->count() > 1)
                <div>
                    <label class="{{ $label }}">Punto de venta</label>
                    <select wire:model="punto_venta" class="{{ $inputClass }}">
                        @foreach ($this->puntosVentaOpciones as $pv)
                            <option value="{{ $pv->numero }}">{{ $pv->label() }}</option>
                        @endforeach
                    </select>
                    @error('punto_venta') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
            @endif
        </div>

        <div class="space-y-3 pt-4 md:pt-0 border-t md:border-t-0 md:border-l md:pl-8 border-gray-200/70 dark:border-gray-700/60">
            <h3 class="{{ $sectionTitle }}">Cliente</h3>

            <div>
                <x-client-picker
                    :client-name="$clients->firstWhere('id', $client_id)?->name ?? '—'"
                    :client-query="$clientQuery"
                    :client-results="$this->clientResults"
                    :show-label="false"
                />
                @error('client_id') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        @if (config('features.price_lists'))
            <div class="space-y-3 pt-4 md:pt-0 border-t md:border-t-0 md:border-l md:pl-8 border-gray-200/70 dark:border-gray-700/60">
                <h3 class="{{ $sectionTitle }}">Lista de precios</h3>
                <div>
                    <select wire:model.live="price_list_id" class="{{ $inputClass }}">
                        <option value="">Precio base</option>
                        @foreach ($priceLists as $list)
                            <option value="{{ $list->id }}">{{ $list->name }} ({{ (float) $list->adjustment_percent > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($list->adjustment_percent, 2), '0'), '.') }}%)</option>
                        @endforeach
                    </select>
                </div>
            </div>
        @endif
      </div>
        <div class="space-y-3">
            <div class="rounded-xl bg-white/80 dark:bg-gray-800/40 border border-gray-200/80 dark:border-gray-700/60 p-4 space-y-3">
                <div class="flex items-center justify-between flex-wrap gap-3">
                    <h3 class="{{ $sectionTitle }}">Productos ({{ $this->itemsCount() }})</h3>
                    @if (count($cart) > 0)
                        <button wire:click="vaciar" class="text-xs text-gray-400 hover:text-red-600 dark:text-gray-500 dark:hover:text-red-400 font-medium">Vaciar</button>
                    @endif
                </div>

                <div class="relative">
                    <div class="absolute left-4 top-1/2 -translate-y-1/2 flex items-center justify-center w-8 h-8 rounded-lg bg-gradient-to-br from-sky-500 to-sky-600 text-white shadow-sm">
                        <x-heroicon-o-qr-code class="w-5 h-5" />
                    </div>
                    <input
                        type="text"
                        autofocus
                        autocomplete="off"
                        wire:model.live.debounce.200ms="barcode"
                        wire:keydown.enter.prevent="addByBarcode"
                        placeholder="Escaneá el código de barras, o escribí para buscar por nombre/SKU"
                        class="w-full rounded-xl border-2 border-sky-300 dark:border-sky-700 dark:bg-gray-900 dark:text-gray-100 pl-14 pr-4 py-4 text-base focus:outline-none focus:ring-4 focus:ring-sky-500/30 focus:border-sky-500 shadow-sm"
                    >
                    @error('barcode') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror

                    @if (trim($barcode) !== '')
                        @php
                            // Resuelto una sola vez para los hasta 8 resultados
                            // de abajo: CurrentSucursal::id() no está memoizado
                            // (puede volver a hacer falta si algún día cambia
                            // dentro del mismo request), así que se evita
                            // llamarlo una vez por producto.
                            $sucursalActivaId = \App\Support\CurrentSucursal::id();
                        @endphp
                        <div class="absolute z-20 mt-1 w-full bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-lg max-h-72 overflow-y-auto">
                            @forelse ($this->barcodeResults as $product)
                                <button
                                    type="button"
                                    wire:key="barcode-result-{{ $product->id }}"
                                    wire:click="selectFromBarcode({{ $product->id }})"
                                    class="w-full flex items-center justify-between gap-3 px-4 py-2.5 text-left hover:bg-sky-50/70 dark:hover:bg-sky-500/10 border-b border-gray-50 dark:border-gray-800/60 last:border-0 transition-colors"
                                >
                                    <span class="min-w-0">
                                        <span class="block text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $product->name }}</span>
                                        <span class="block text-xs text-gray-500 dark:text-gray-400">
                                            Stock: {{ $product->stockEnSucursal($sucursalActivaId) }}{{ $product->sku ? " · SKU: {$product->sku}" : '' }} · ${{ money($product->priceForList($this->currentPriceList())) }}
                                        </span>
                                    </span>
                                </button>
                            @empty
                                <p class="p-4 text-sm text-gray-400 dark:text-gray-500">Sin resultados para "{{ $barcode }}".</p>
                            @endforelse
                        </div>
                    @endif
                </div>
            </div>

            <div class="border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden bg-white dark:bg-gray-900/60">
                <div class="divide-y divide-gray-100 dark:divide-gray-800 min-h-[16rem] lg:min-h-[calc(100vh-20rem)]">
                    @forelse ($cart as $index => $item)
                        <div wire:key="cart-{{ $index }}" class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3 px-4 py-3 hover:bg-sky-50/40 dark:hover:bg-sky-500/5 transition-colors">
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
                                    <span>${{ money($item['unit_price']) }} c/u</span>
                                </div>
                            </div>
                            <div class="flex items-center justify-between gap-3 sm:contents">
                                @if ($item['by_weight'] ?? false)
                                    <div class="flex items-center gap-1 shrink-0 rounded-lg bg-amber-50 dark:bg-amber-500/10 px-3 py-1.5">
                                        <x-heroicon-o-scale class="w-3.5 h-3.5 text-amber-600 dark:text-amber-400" />
                                        <span class="text-sm font-bold text-amber-700 dark:text-amber-400">{{ number_format($item['quantity'], 3) }} kg</span>
                                    </div>
                                @else
                                    <div class="flex items-center gap-1 shrink-0 rounded-lg bg-gray-100 dark:bg-gray-800 p-0.5">
                                        <button wire:click="dec({{ $index }})" class="w-10 h-10 sm:w-8 sm:h-8 rounded-md bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-300 hover:text-sky-600 dark:hover:text-sky-400 shadow-sm flex items-center justify-center text-lg font-medium">−</button>
                                        <span class="w-8 text-center text-sm font-bold text-gray-900 dark:text-gray-100">{{ $item['quantity'] }}</span>
                                        <button wire:click="inc({{ $index }})" class="w-10 h-10 sm:w-8 sm:h-8 rounded-md bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-300 hover:text-sky-600 dark:hover:text-sky-400 shadow-sm flex items-center justify-center text-lg font-medium">+</button>
                                    </div>
                                @endif
                                <span class="text-right text-base font-bold text-gray-900 dark:text-gray-100 shrink-0 sm:w-24">
                                    ${{ money($this->lineTotal($item)) }}
                                </span>
                                <button wire:click="removeItem({{ $index }})" aria-label="Quitar producto del carrito" class="shrink-0 text-gray-300 hover:text-red-500 dark:text-gray-600 dark:hover:text-red-400 p-1">
                                    <x-heroicon-o-x-mark class="w-5 h-5" />
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="flex flex-col items-center justify-center h-64 text-center text-gray-400 dark:text-gray-500 px-4">
                            <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-sky-100 to-sky-50 dark:from-gray-800 dark:to-gray-800/50 flex items-center justify-center mb-3">
                                <x-heroicon-o-qr-code class="w-8 h-8 text-sky-400 dark:text-gray-600" />
                            </div>
                            <p class="text-sm font-medium">Escaneá un producto para empezar</p>
                            <p class="text-xs mt-0.5">Pasá el código por el lector o escribilo y apretá Enter.</p>
                        </div>
                    @endforelse
                </div>
            </div>
            @error('cart') <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>
        </div>
        {{-- /Columna izquierda --}}

        {{-- Columna derecha: checkout --}}
        <div class="lg:col-span-4 lg:sticky lg:top-4 self-start mt-6 pt-6 border-t lg:mt-0 lg:pt-0 lg:border-t-0 lg:border-l lg:pl-8 border-sky-200/70 dark:border-gray-800">

            {{-- Grupo: cobro (medios de pago, mis descuentos y vale), card neutra propia para separarlo visualmente del total y del botón de cobrar. --}}
            <div class="rounded-xl bg-white/80 dark:bg-gray-800/40 border border-gray-200/80 dark:border-gray-700/60 p-4 space-y-4">

                {{-- Sección: Medios de pago --}}
                <div class="space-y-3">
                    <h3 class="{{ $sectionTitle }}">Medios de pago</h3>

                    {{--
                        Un solo select + un solo input "en preparación"
                        (stagingMethod/stagingAmount), reutilizados para cada
                        pago que se agrega a la lista de abajo — en vez de un
                        renglón editable por cada medio ya cargado (diseño
                        anterior). Con varios <select> con wire:model activos
                        a la vez en la misma pantalla, en Firefox, después de
                        unos cuantos repintados seguidos, los clicks en
                        "Agregar"/"Cobrar" dejaban de responder (bug real,
                        reproducido 2026-10-20). Con un solo par de campos
                        reutilizado y los pagos ya cargados mostrados de solo
                        lectura (sin ningún wire:model propio, solo un botón
                        de borrar), baja mucho la cantidad de controles
                        interactivos vivos en la pantalla a la vez.
                    --}}
                    <div class="flex items-center gap-2">
                        <select wire:model="stagingMethod" class="w-1/2 {{ $inputClass }}">
                            <option value="">Elegí un medio</option>
                            @foreach ($paymentMethods as $method)
                                <option value="{{ $method->value }}">{{ $method->label() }}</option>
                            @endforeach
                        </select>
                        <input type="number" min="0" step="0.01" wire:model="stagingAmount" wire:keydown.enter="addPayment" placeholder="Monto" class="w-1/2 {{ $inputClass }} text-right">
                    </div>
                    @error('stagingMethod') <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    @error('stagingAmount') <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                    <button type="button" wire:click="addPayment" class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg bg-sky-600 hover:bg-sky-700 px-3 py-1.5 text-sm font-semibold text-white shadow-sm active:scale-[0.98] transition-all">
                        <x-heroicon-o-plus class="w-4 h-4" /> Agregar a la lista
                    </button>

                    @forelse ($payments as $index => $payment)
                        <div wire:key="pay-{{ $index }}" class="flex items-center justify-between gap-2 rounded-lg bg-gray-50 dark:bg-gray-800/60 px-3 py-2 text-sm">
                            <span class="text-gray-700 dark:text-gray-200">
                                {{ collect($paymentMethods)->first(fn ($m) => $m->value === $payment['method'])?->label() ?? $payment['method'] }}
                                <span class="font-semibold">${{ money((float) $payment['amount']) }}</span>
                                @if ($this->paymentDiscountPct($payment) > 0)
                                    <span class="block text-xs text-emerald-600 dark:text-emerald-400">
                                        -{{ rtrim(rtrim(number_format($this->paymentDiscountPct($payment), 2), '0'), '.') }}% → cobrás ${{ money($this->montoRealPago($payment)) }}
                                    </span>
                                @endif
                            </span>
                            <button type="button" wire:click="removePayment({{ $index }})" aria-label="Quitar medio de pago" class="shrink-0 text-gray-300 hover:text-red-500 dark:text-gray-600 dark:hover:text-red-400">
                                <x-heroicon-o-x-mark class="w-5 h-5" />
                            </button>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400 dark:text-gray-500">Sin pago cargado: la venta queda como saldo en la cuenta corriente del cliente.</p>
                    @endforelse
                    @error('payments') <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                    @if (count($payments) > 0)
                        <div class="flex items-center justify-between text-sm pt-1">
                            <span class="text-gray-500 dark:text-gray-400">Pagado (precio de lista)</span>
                            <span class="font-medium text-gray-900 dark:text-gray-100">${{ money($this->paymentsTotal()) }}</span>
                        </div>
                    @endif
                    @if ($this->totalConDescuentoPorMedioDePago() !== null)
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-emerald-600 dark:text-emerald-400 font-medium">Total a cobrar con descuento</span>
                            <span class="font-bold text-emerald-600 dark:text-emerald-400">${{ money($this->totalConDescuentoPorMedioDePago()) }}</span>
                        </div>
                    @endif
                    @if ($this->saldoPendiente() > 0)
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-amber-600 dark:text-amber-400">Saldo a cuenta corriente</span>
                            <span class="font-semibold text-amber-600 dark:text-amber-400">${{ money($this->saldoPendiente()) }}</span>
                        </div>
                    @endif
                </div>

                @if ($this->discountsActivos->isNotEmpty() || $discount_id)
                    {{-- Mis Descuentos: descuento manual global elegido por el cajero, vigente hoy. --}}
                    <div class="space-y-1.5 pt-4 border-t border-gray-200/70 dark:border-gray-700/60">
                        <h3 class="{{ $sectionTitle }} mb-1">Mis Descuentos</h3>
                        <div class="flex items-center gap-2">
                            <select wire:model.live="discount_id" class="flex-1 min-w-0 {{ $inputClass }}">
                                <option value="">Sin descuento</option>
                                @foreach ($this->discountsActivos as $discount)
                                    <option wire:key="discount-{{ $discount->id }}" value="{{ $discount->id }}">{{ $discount->name }} ({{ $discount->shortLabel() }})</option>
                                @endforeach
                            </select>
                            @if ($discount_id)
                                <button type="button" wire:click="$set('discount_id', null)" class="text-gray-400 hover:text-red-600">
                                    <x-heroicon-o-x-mark class="w-4 h-4" />
                                </button>
                            @endif
                        </div>
                    </div>
                @endif

                @if (config('features.vale_cambio'))
                    {{-- Canje de vale de cambio: independiente del tipo de comprobante, sirve en cualquier venta.
                         Oculto detrás de un check (poca gente lo usa) — salvo que ya haya uno aplicado, en cuyo
                         caso arranca abierto para no esconder algo que ya está cargado. --}}
                    <div class="pt-4 border-t border-gray-200/70 dark:border-gray-700/60" x-data="{ mostrarVale: @js((bool) $valeEncontrado) }">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" x-model="mostrarVale" class="rounded border-gray-300 dark:border-gray-700 text-sky-600 focus:ring-sky-500">
                            <span class="{{ $sectionTitle }}">¿Cliente trae un vale de cambio?</span>
                        </label>
                        <div x-show="mostrarVale" x-cloak class="space-y-1.5 mt-2">
                            @if (! $valeEncontrado)
                                <div class="flex items-center gap-2">
                                    <input type="text" wire:model="vale_codigo" placeholder="Código de vale" class="flex-1 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/60 px-3 py-2 text-sm uppercase dark:text-gray-100">
                                    <button type="button" wire:click="buscarVale" class="shrink-0 rounded-lg bg-sky-600 hover:bg-sky-700 px-3 py-2 text-xs font-medium text-white">
                                        Canjear vale
                                    </button>
                                </div>
                                @error('vale_codigo') <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            @else
                                <div class="flex items-center justify-between rounded-lg border border-emerald-200 dark:border-emerald-500/20 bg-emerald-50 dark:bg-emerald-500/5 px-3 py-2">
                                    <div class="text-sm">
                                        <span class="font-semibold text-emerald-700 dark:text-emerald-400">Vale {{ $valeEncontrado->code }}</span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400 block">Saldo disponible: ${{ money($valeEncontrado->balance) }}</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <input type="number" min="0" step="0.01" wire:model.live="vale_monto" class="w-24 rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-2 py-1 text-sm text-right dark:text-gray-100">
                                        <button type="button" wire:click="quitarVale" aria-label="Quitar vale de cambio" class="text-gray-400 hover:text-red-600">
                                            <x-heroicon-o-x-mark class="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            {{-- Total: tonalidad propia (índigo) para que resalte del grupo de arriba y del botón de cobrar. --}}
            <div class="rounded-xl bg-indigo-50 dark:bg-indigo-500/10 border border-indigo-200/70 dark:border-indigo-500/20 p-4 mt-4">
                @if ($this->descuentosTotal() > 0.004)
                    <div class="text-sm space-y-1.5 mb-3">
                        <div class="flex justify-between text-gray-600 dark:text-gray-400">
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
                        @if ($this->descuentoManualAplicado())
                            <div class="flex items-center justify-between text-sm">
                                <span class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400 font-medium">
                                    <x-heroicon-o-receipt-percent class="w-3.5 h-3.5" /> {{ $this->descuentoManualAplicado()->name }}
                                </span>
                                <span class="text-emerald-600 dark:text-emerald-400 font-medium">−${{ money($this->descuentoManualMonto()) }}</span>
                            </div>
                        @endif
                        <div class="flex items-center justify-between font-semibold text-emerald-600 dark:text-emerald-400">
                            <span>Descuento total</span>
                            <span>−${{ money($this->descuentosTotal()) }}</span>
                        </div>
                    </div>
                @endif
                <div class="flex items-start justify-between gap-2">
                    <div class="text-xs font-medium text-indigo-600/80 dark:text-indigo-400/80">
                        <span class="block">Total a cobrar</span>
                        <span class="block text-[10px] text-indigo-500/70 dark:text-indigo-400/60">({{ $this->itemsCount() }} art.)</span>
                    </div>
                    <span class="text-3xl font-extrabold text-indigo-700 dark:text-indigo-400 text-right">${{ money($this->total()) }}</span>
                </div>
            </div>

            {{-- Imprimir + Cobrar: tonalidad propia (verde), es la acción final de la pantalla. --}}
            <div class="rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200/70 dark:border-emerald-500/20 p-4 mt-4 space-y-3">
                <label class="flex items-center gap-2 text-sm text-emerald-800 dark:text-emerald-300">
                    <input type="checkbox" wire:model="printOnSale" class="rounded border-emerald-300 dark:border-emerald-700 text-emerald-600 focus:ring-emerald-500">
                    Imprimir ticket
                </label>

                @if (config('features.vale_cambio'))
                    <label class="flex items-center gap-2 text-sm text-emerald-800 dark:text-emerald-300">
                        <input type="checkbox" wire:model="printExchangeSlip" class="rounded border-emerald-300 dark:border-emerald-700 text-emerald-600 focus:ring-emerald-500">
                        Ticket de cambio
                    </label>
                @endif

                <button
                    wire:click="cobrar"
                    wire:loading.attr="disabled"
                    wire:target="cobrar"
                    @disabled(count($cart) === 0 && empty($itemsADevolver))
                    class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 px-4 py-3.5 text-base font-semibold text-white shadow-sm disabled:opacity-50 disabled:cursor-not-allowed transition-all"
                >
                    <x-heroicon-o-banknotes class="w-5 h-5" />
                    <span wire:loading.remove wire:target="cobrar">
                        @if ($itemsADevolver !== [] && count($cart) === 0)
                            Confirmar devolución
                        @else
                            Cobrar ${{ money($this->montoACobrar()) }}
                        @endif
                    </span>
                    <span wire:loading wire:target="cobrar">Cobrando...</span>
                </button>
            </div>
        </div>
        {{-- /Columna derecha --}}
      </div>
      {{-- /grid --}}
    </div>
</div>
