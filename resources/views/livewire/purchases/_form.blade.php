@php
    $inputClass = 'w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 focus:bg-white dark:focus:bg-gray-800 transition';
    $cellInputClass = 'w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 dark:text-gray-100 px-2 py-1.5 text-sm shadow-sm hover:border-indigo-300 dark:hover:border-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 transition';
    $smallCellInputClass = 'rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 dark:text-gray-100 px-2 py-1 text-xs shadow-sm hover:border-indigo-300 dark:hover:border-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 transition';
@endphp

<form wire:submit="save" class="max-w-6xl">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-start">
        {{-- Columna izquierda: buscador de productos + lista --}}
        <div class="lg:col-span-8 order-2 lg:order-1 space-y-3">
            @if ($sin_detalle)
                <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm p-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Total de la compra *</label>
                    <input type="number" min="0.01" step="0.01" wire:model.live="manual_total" placeholder="0.00" class="{{ $inputClass }} sm:max-w-xs">
                    @error('manual_total') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1.5">No se carga stock de productos: la compra queda registrada solo por su total, para cuenta corriente del proveedor.</p>
                </div>
            @else
            <div class="relative">
                <div class="absolute left-4 top-1/2 -translate-y-1/2 flex items-center justify-center w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-sm">
                    <x-heroicon-o-magnifying-glass class="w-5 h-5" />
                </div>
                <input
                    type="text"
                    wire:model.live.debounce.200ms="productQuery"
                    placeholder="Buscar producto por nombre o SKU..."
                    class="w-full rounded-xl border-2 border-indigo-300 dark:border-indigo-700 dark:bg-gray-900 dark:text-gray-100 pl-14 pr-4 py-4 text-base focus:outline-none focus:ring-4 focus:ring-indigo-500/30 focus:border-indigo-500 shadow-sm"
                >
                @error('items') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror

                @if (trim($productQuery) !== '')
                    <div class="absolute z-20 mt-1 w-full bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-lg max-h-72 overflow-y-auto">
                        @forelse ($this->productResults as $product)
                            <button
                                type="button"
                                wire:click="addProductItem({{ $product->id }})"
                                class="w-full flex items-center justify-between gap-3 px-4 py-2.5 text-left hover:bg-indigo-50/70 dark:hover:bg-indigo-500/10 border-b border-gray-50 dark:border-gray-800/60 last:border-0 transition-colors"
                            >
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $product->name }}</span>
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">
                                        {{ $product->sku ? "SKU: {$product->sku} · " : '' }}${{ money($product->price) }}
                                    </span>
                                </span>
                            </button>
                        @empty
                            <p class="p-4 text-sm text-gray-400 dark:text-gray-500">Sin resultados para "{{ $productQuery }}".</p>
                        @endforelse
                    </div>
                @endif
            </div>

            <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 overflow-hidden shadow-sm">
                <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-indigo-600 to-violet-600 text-white">
                    <h2 class="text-sm font-semibold inline-flex items-center gap-2">
                        <x-heroicon-o-shopping-cart class="w-4 h-4" /> Productos ({{ count($items) }})
                    </h2>
                </div>

                @if (count($items) > 0)
                    <div class="hidden sm:block overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr class="text-left text-gray-500 dark:text-gray-400">
                                <th class="px-4 py-2 font-medium">Descripción</th>
                                <th class="px-4 py-2 font-medium w-24">Cant.</th>
                                <th class="px-4 py-2 font-medium w-32">Precio unit.</th>
                                <th class="px-4 py-2 font-medium w-28 text-right">Total</th>
                                <th class="px-2 py-2 w-10"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($items as $index => $item)
                                <tr wire:key="item-{{ $index }}" class="border-t border-gray-100 dark:border-gray-800">
                                    <td class="px-4 py-2">
                                        <input type="text" wire:model="items.{{ $index }}.description" class="{{ $cellInputClass }}">
                                    </td>
                                    <td class="px-4 py-2">
                                        <input type="number" min="0.01" step="1" wire:model="items.{{ $index }}.quantity" class="{{ $cellInputClass }}">
                                    </td>
                                    <td class="px-4 py-2">
                                        <input type="number" min="0" step="0.01" wire:model="items.{{ $index }}.unit_price" class="{{ $cellInputClass }}">
                                    </td>
                                    <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300">
                                        ${{ money((float) $item['quantity'] * (float) $item['unit_price']) }}
                                    </td>
                                    <td class="px-2 py-2 text-center">
                                        <button type="button" wire:click="removeItem({{ $index }})" class="text-gray-300 hover:text-red-500 dark:text-gray-600 dark:hover:text-red-400">
                                            <x-heroicon-o-trash class="w-4 h-4" />
                                        </button>
                                    </td>
                                </tr>
                                @if ($showBatchFields ?? false)
                                    <tr wire:key="item-batch-{{ $index }}" class="border-t border-gray-50 dark:border-gray-800/50 bg-gray-50/60 dark:bg-gray-800/20">
                                        <td colspan="5" class="px-4 py-2">
                                            <div class="flex flex-wrap items-center gap-3 text-xs">
                                                <span class="text-gray-400 dark:text-gray-500">Lote / vencimiento (opcional, para productos perecederos):</span>
                                                <input type="text" wire:model="items.{{ $index }}.batch_number" placeholder="N° de lote" class="w-32 {{ $smallCellInputClass }}">
                                                <input type="date" wire:model="items.{{ $index }}.expiration_date" class="{{ $smallCellInputClass }}">
                                            </div>
                                            @error("items.{$index}.expiration_date") <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                    </div>

                    <div class="sm:hidden divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($items as $index => $item)
                            <div wire:key="item-card-{{ $index }}" class="p-3 space-y-2">
                                <div class="flex items-start gap-2">
                                    <input type="text" wire:model="items.{{ $index }}.description" placeholder="Descripción" class="flex-1 {{ $cellInputClass }}">
                                    <button type="button" wire:click="removeItem({{ $index }})" class="p-1.5 text-gray-300 hover:text-red-500 dark:text-gray-600 dark:hover:text-red-400 shrink-0">
                                        <x-heroicon-o-trash class="w-4 h-4" />
                                    </button>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-xs text-gray-400 dark:text-gray-500 mb-0.5">Cantidad</label>
                                        <input type="number" min="0.01" step="1" wire:model="items.{{ $index }}.quantity" class="{{ $cellInputClass }}">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-400 dark:text-gray-500 mb-0.5">Precio unit.</label>
                                        <input type="number" min="0" step="0.01" wire:model="items.{{ $index }}.unit_price" class="{{ $cellInputClass }}">
                                    </div>
                                </div>
                                @if ($showBatchFields ?? false)
                                    <div class="pt-1 space-y-1">
                                        <span class="block text-xs text-gray-400 dark:text-gray-500">Lote / vencimiento (opcional, para productos perecederos):</span>
                                        <div class="flex gap-2">
                                            <input type="text" wire:model="items.{{ $index }}.batch_number" placeholder="N° de lote" class="flex-1 {{ $smallCellInputClass }}">
                                            <input type="date" wire:model="items.{{ $index }}.expiration_date" class="{{ $smallCellInputClass }}">
                                        </div>
                                        @error("items.{$index}.expiration_date") <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                                    </div>
                                @endif
                                <div class="flex justify-between text-sm font-medium text-gray-700 dark:text-gray-300 pt-1">
                                    <span>Total</span>
                                    <span>${{ money((float) $item['quantity'] * (float) $item['unit_price']) }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center h-40 text-center text-gray-400 dark:text-gray-500 px-4">
                        <x-heroicon-o-magnifying-glass class="w-8 h-8 text-gray-300 dark:text-gray-700 mb-2" />
                        <p class="text-sm font-medium">Todavía no agregaste productos</p>
                        <p class="text-xs mt-0.5">Buscá arriba por nombre o SKU para agregarlos.</p>
                    </div>
                @endif
            </div>
            <p class="text-xs text-gray-400 dark:text-gray-500">Al guardar la compra, el stock de cada producto se incrementa automáticamente.</p>

            {{-- Impuestos y percepciones (se cargan como vienen en la factura del proveedor) --}}
            <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm p-4">
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Impuestos y percepciones</label>
                    <button type="button" wire:click="addTax" class="inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium">
                        <x-heroicon-o-plus class="w-4 h-4" /> Agregar impuesto
                    </button>
                </div>

                <datalist id="conceptos-impuestos">
                    <option value="Percepción IVA"></option>
                    <option value="Percepción IIBB"></option>
                    <option value="Percepción Ganancias"></option>
                    <option value="Impuestos internos"></option>
                    <option value="IVA 10,5% adicional"></option>
                </datalist>

                @if (count($taxes) === 0)
                    <p class="text-sm text-gray-400 dark:text-gray-500">Sin percepciones. Agregá las que figuren en la factura del proveedor (IIBB, percepción IVA, etc.).</p>
                @else
                    <div class="space-y-2">
                        @foreach ($taxes as $index => $tax)
                            <div class="flex items-center gap-2">
                                <input
                                    type="text"
                                    list="conceptos-impuestos"
                                    wire:model="taxes.{{ $index }}.concepto"
                                    placeholder="Concepto (ej. Percepción IIBB)"
                                    class="flex-1 {{ $cellInputClass }}"
                                >
                                <input
                                    type="number" min="0" step="0.01"
                                    wire:model.live="taxes.{{ $index }}.amount"
                                    placeholder="Monto"
                                    class="w-32 {{ $cellInputClass }} text-right"
                                >
                                <button type="button" wire:click="removeTax({{ $index }})" class="text-gray-300 hover:text-red-500 dark:text-gray-600 dark:hover:text-red-400">
                                    <x-heroicon-o-trash class="w-4 h-4" />
                                </button>
                            </div>
                            @error("taxes.{$index}.concepto") <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        @endforeach
                    </div>
                @endif
            </div>
            @endif
        </div>

        {{-- Columna derecha: card celeste con todos los datos --}}
        <div class="lg:col-span-4 order-1 lg:order-2">
            <div class="lg:sticky lg:top-4 rounded-2xl border border-sky-100 dark:border-gray-800 bg-gradient-to-b from-white to-sky-50/70 dark:from-gray-900 dark:to-gray-950 shadow-md shadow-sky-100/50 dark:shadow-black/30 p-4 space-y-3">
                <div>
                    <x-provider-picker
                        :provider-name="$selectedProviderName ?? '—'"
                        :provider-query="$providerQuery"
                        :provider-results="$this->providerResults"
                    />
                    @error('provider_id') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Fecha de compra</label>
                        <input type="date" wire:model="issue_date" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Fecha de vto.</label>
                        <input type="date" wire:model="due_date" class="{{ $inputClass }}">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Tipo de comprobante *</label>
                    <select wire:model.live="tipo_comprobante" class="{{ $inputClass }}">
                        @foreach ($tiposComprobante as $tipo)
                            <option value="{{ $tipo->value }}">{{ $tipo->label() }}</option>
                        @endforeach
                    </select>
                    @error('tipo_comprobante') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                    @if ((int) $tipo_comprobante === \App\Enums\TipoComprobante::Remito->value)
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">No entra al Libro IVA Compras hasta cargar la factura real.</p>
                    @endif
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Punto de venta *</label>
                        <input type="number" min="1" max="9999" wire:model="punto_venta" class="{{ $inputClass }}">
                        @error('punto_venta') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">N° comprobante *</label>
                        <input type="number" min="1" max="99999999" wire:model="numero_comprobante" class="{{ $inputClass }}">
                        @error('numero_comprobante') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">N° de remito</label>
                    <input type="text" wire:model="remito_number" maxlength="60" placeholder="Ej: 0001-00012345" class="{{ $inputClass }}">
                    @error('remito_number') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>

                <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 px-1 cursor-pointer">
                    <input type="checkbox" wire:model.live="sin_detalle" class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 focus:ring-indigo-500">
                    Compra sin detalle (solo el total)
                </label>

                <div class="rounded-2xl overflow-hidden border border-indigo-100 dark:border-indigo-500/20 shadow-sm">
                    @if (! $sin_detalle)
                        <div class="bg-white/70 dark:bg-gray-900/40 px-4 py-3 space-y-1.5 text-sm">
                            <div class="flex justify-between text-gray-600 dark:text-gray-400">
                                <span>Subtotal</span>
                                <span>${{ money($this->subtotal()) }}</span>
                            </div>
                            <div class="flex justify-between items-center text-gray-600 dark:text-gray-400">
                                <span>Impuesto (%)</span>
                                <input type="number" min="0" step="0.01" wire:model.live="tax_rate" class="w-20 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 dark:text-gray-100 px-2 py-1 text-sm text-right shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 transition">
                            </div>
                            <div class="flex justify-between text-gray-600 dark:text-gray-400">
                                <span>Monto impuesto</span>
                                <span>${{ money($this->taxAmount()) }}</span>
                            </div>
                            @if ($this->percepcionesTotal() > 0)
                                <div class="flex justify-between text-gray-600 dark:text-gray-400">
                                    <span>Percepciones</span>
                                    <span>${{ money($this->percepcionesTotal()) }}</span>
                                </div>
                            @endif
                        </div>
                    @endif
                    <div class="bg-gradient-to-br from-indigo-600 to-violet-700 px-4 py-3 flex items-end justify-between text-white">
                        <span class="text-xs font-medium uppercase tracking-wide text-white/80">Total</span>
                        <span class="text-2xl font-extrabold tracking-tight">${{ money($this->total()) }}</span>
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Métodos de pago</span>
                        <button type="button" wire:click="addPayment" class="inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 font-medium">
                            <x-heroicon-o-plus class="w-4 h-4" /> Agregar
                        </button>
                    </div>

                    @forelse ($payments as $index => $payment)
                        <div wire:key="payment-{{ $index }}" class="flex items-center gap-2">
                            <select wire:model="payments.{{ $index }}.method" class="flex-1 {{ $inputClass }}">
                                @foreach ($paymentMethods as $method)
                                    <option value="{{ $method->value }}">{{ $method->label() }}</option>
                                @endforeach
                            </select>
                            <input type="number" min="0" step="0.01" wire:model.live="payments.{{ $index }}.amount" class="w-24 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60 px-3 py-2.5 text-sm text-right dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white dark:focus:bg-gray-800 transition">
                            <button type="button" wire:click="removePayment({{ $index }})" class="text-gray-400 hover:text-red-600 dark:hover:text-red-400 shrink-0">
                                <x-heroicon-o-x-mark class="w-5 h-5" />
                            </button>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400 dark:text-gray-500">Sin pago cargado: queda en cuenta corriente del proveedor.</p>
                    @endforelse
                    @error('payments') <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                    <p class="text-xs {{ $this->remaining() > 0.005 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400 dark:text-gray-500' }}">
                        Pagado: ${{ money($this->paidTotal()) }} de ${{ money($this->total()) }}
                        @if ($this->remaining() > 0.005) · Resta ${{ money($this->remaining()) }} @endif
                    </p>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Estado</label>
                    <select wire:model="status" class="{{ $inputClass }}">
                        @foreach ($statuses as $s)
                            <option value="{{ $s->value }}">{{ $s->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Notas</label>
                    <textarea wire:model="notes" rows="2" placeholder="Condiciones de pago, etc." class="{{ $inputClass }}"></textarea>
                </div>

                <div class="flex gap-2 pt-1">
                    <button type="submit" wire:loading.attr="disabled" wire:target="save" class="flex-1 inline-flex items-center justify-center rounded-xl bg-gradient-to-r from-indigo-600 to-indigo-500 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-600/30 hover:from-indigo-700 hover:to-indigo-600 active:scale-[0.99] disabled:opacity-50 transition-all">
                        {{ $submitLabel }}
                    </button>
                    <a href="{{ route('purchases.index') }}" wire:navigate class="inline-flex items-center justify-center rounded-xl border border-gray-300 dark:border-gray-700 px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-all">
                        Cancelar
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>
