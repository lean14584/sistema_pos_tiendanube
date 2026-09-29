@php
    $inputClass = 'w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/60 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-400 focus:border-sky-400 transition';
    $cellInputClass = 'w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 dark:text-gray-100 px-2 py-1.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-400 focus:border-sky-400 transition';
    $smallCellInputClass = 'rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 dark:text-gray-100 px-2 py-1 text-xs shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-400 focus:border-sky-400 transition';
    $sectionTitle = 'text-xs font-bold text-sky-700 dark:text-sky-400 uppercase tracking-wider';
    $sectionDivider = 'border-t border-sky-200/70 dark:border-gray-800 pt-5 mt-5';
    $label = 'block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1';
@endphp

<form wire:submit="save" class="max-w-5xl">
    <div class="rounded-2xl border border-sky-200 dark:border-gray-800 bg-sky-100/70 dark:bg-gray-900 shadow-sm p-5 sm:p-7">
      <div class="grid grid-cols-1 lg:grid-cols-12 lg:gap-x-8">
        {{-- Columna izquierda: Productos --}}
        <div class="lg:col-span-8">
        {{-- Sección: Productos --}}
        <div class="space-y-3">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <h3 class="{{ $sectionTitle }}">Productos ({{ count($items) }})</h3>
                <div class="flex items-center gap-3 flex-wrap">
                    <input type="text" wire:model="remito_number" maxlength="60" placeholder="N° de remito" class="w-40 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 dark:text-gray-100 px-2.5 py-1.5 text-xs shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-400 focus:border-sky-400 transition">
                    <label class="inline-flex items-center gap-1.5 cursor-pointer">
                        <input type="checkbox" wire:model.live="sin_detalle" class="rounded border-gray-300 dark:border-gray-700 text-sky-600 focus:ring-sky-500">
                        <span class="text-xs font-medium text-gray-600 dark:text-gray-400">Compra sin detalle (solo el total)</span>
                    </label>
                </div>
            </div>
            @error('remito_number') <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

            @if ($sin_detalle)
                <div>
                    <label class="{{ $label }}">Total de la compra *</label>
                    <input type="number" min="0.01" step="0.01" wire:model.live="manual_total" placeholder="0.00" class="{{ $inputClass }} sm:max-w-xs">
                    @error('manual_total') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">No se carga stock de productos: la compra queda registrada solo por su total, para cuenta corriente del proveedor.</p>
                </div>
            @else
                <div class="relative">
                    <x-heroicon-o-magnifying-glass class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
                    <input
                        type="text"
                        wire:model.live.debounce.200ms="productQuery"
                        placeholder="Buscar producto por nombre o SKU..."
                        class="{{ $inputClass }} pl-9"
                    >
                    @error('items') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror

                    @if (trim($productQuery) !== '')
                        <div class="absolute z-20 mt-1 w-full bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-lg max-h-64 overflow-y-auto">
                            @forelse ($this->productResults as $product)
                                <button
                                    type="button"
                                    wire:click="addProductItem({{ $product->id }})"
                                    class="w-full flex items-center justify-between gap-3 px-3 py-2 text-left hover:bg-sky-50 dark:hover:bg-sky-500/10 transition-colors"
                                >
                                    <span class="min-w-0">
                                        <span class="block text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $product->name }}</span>
                                        <span class="block text-xs text-gray-500 dark:text-gray-400">
                                            {{ $product->sku ? "SKU: {$product->sku} · " : '' }}${{ money($product->price) }}
                                        </span>
                                    </span>
                                </button>
                            @empty
                                <p class="p-3 text-sm text-gray-400 dark:text-gray-500">Sin resultados para "{{ $productQuery }}".</p>
                            @endforelse
                        </div>
                    @endif
                </div>

                @if (count($items) > 0)
                    <div class="border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden bg-white dark:bg-gray-900/60">
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
                    </div>
                @else
                    <p class="text-sm text-gray-400 dark:text-gray-500">Todavía no agregaste productos a esta compra.</p>
                @endif
                <p class="text-xs text-gray-500 dark:text-gray-400">Al guardar la compra, el stock de cada producto se incrementa automáticamente.</p>

                {{-- Resumen de totales, pegado a la lista de productos --}}
                <div class="flex justify-end">
                    <div class="w-full max-w-xs space-y-1.5 text-sm rounded-xl border border-sky-300 dark:border-sky-500/30 bg-sky-100 dark:bg-sky-500/20 p-4">
                        <div class="flex justify-between text-gray-600 dark:text-gray-400">
                            <span>Subtotal</span>
                            <span>${{ money($this->subtotal()) }}</span>
                        </div>
                        <div class="flex justify-between items-center text-gray-600 dark:text-gray-400">
                            <span>Impuesto (%)</span>
                            <input type="number" min="0" step="0.01" wire:model.live="tax_rate" class="w-20 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 dark:text-gray-100 px-2 py-1 text-sm text-right shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-400 focus:border-sky-400 transition">
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
                        <div class="flex justify-between font-semibold text-sky-900 dark:text-sky-100 text-base pt-1.5 border-t border-sky-300/70 dark:border-sky-500/20">
                            <span>Total</span>
                            <span>${{ money($this->total()) }}</span>
                        </div>
                    </div>
                </div>
            @endif
        </div>
        </div>
        {{-- /Columna izquierda --}}

        {{-- Columna derecha: resto de los datos --}}
        <div class="lg:col-span-4 mt-6 pt-6 border-t lg:mt-0 lg:pt-0 lg:border-t-0 lg:border-l lg:pl-8 border-sky-200/70 dark:border-gray-800">

        {{-- Sección: Proveedor y comprobante --}}
        <div class="space-y-4">
            <h3 class="{{ $sectionTitle }}">Proveedor y comprobante</h3>

            <div>
                <x-provider-picker
                    :provider-name="$selectedProviderName ?? '—'"
                    :provider-query="$providerQuery"
                    :provider-results="$this->providerResults"
                />
                @error('provider_id') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="{{ $label }}">Fecha de compra</label>
                <input type="date" wire:model="issue_date" class="{{ $inputClass }}">
            </div>
            <div>
                <label class="{{ $label }}">Fecha de vencimiento</label>
                <input type="date" wire:model="due_date" class="{{ $inputClass }}">
            </div>

            <div>
                <label class="{{ $label }}">Tipo de comprobante *</label>
                <select wire:model.live="tipo_comprobante" class="{{ $inputClass }}">
                    @foreach ($tiposComprobante as $tipo)
                        <option value="{{ $tipo->value }}">{{ $tipo->label() }}</option>
                    @endforeach
                </select>
                @error('tipo_comprobante') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>
            @if ((int) $tipo_comprobante === \App\Enums\TipoComprobante::Remito->value)
                <p class="text-xs text-gray-500 dark:text-gray-400">Datos del remito del proveedor. Esta compra <strong>no entra al Libro IVA Compras</strong> hasta que se cargue la factura real.</p>
            @else
                <p class="text-xs text-gray-500 dark:text-gray-400">Datos del comprobante tal como lo emitió el proveedor (para el Libro IVA Compras), distintos del número interno de esta app.</p>
            @endif
        </div>

        {{-- Sección: Impuestos y percepciones --}}
        @if (! $sin_detalle)
        <div class="space-y-3 {{ $sectionDivider }}">
            <div class="flex items-center justify-between">
                <h3 class="{{ $sectionTitle }}">Impuestos y percepciones</h3>
                <button type="button" wire:click="addTax" class="inline-flex items-center gap-1 text-sm text-sky-700 hover:text-sky-800 dark:text-sky-400 dark:hover:text-sky-300 font-medium">
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
                <div class="space-y-3">
                    @foreach ($taxes as $index => $tax)
                        <div class="space-y-1.5">
                            <div class="flex items-center gap-2">
                                <input
                                    type="text"
                                    list="conceptos-impuestos"
                                    wire:model="taxes.{{ $index }}.concepto"
                                    placeholder="Concepto (ej. Percepción IIBB)"
                                    class="flex-1 min-w-0 {{ $inputClass }}"
                                >
                                <button type="button" wire:click="removeTax({{ $index }})" class="shrink-0 text-gray-300 hover:text-red-500 dark:text-gray-600 dark:hover:text-red-400">
                                    <x-heroicon-o-trash class="w-4 h-4" />
                                </button>
                            </div>
                            <input
                                type="number" min="0" step="0.01"
                                wire:model.live="taxes.{{ $index }}.amount"
                                placeholder="Monto"
                                class="w-full {{ $inputClass }} text-right"
                            >
                            @error("taxes.{$index}.concepto") <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
        @endif

        {{-- Sección: Pago --}}
        <div class="space-y-3 {{ $sectionDivider }}">
            <h3 class="{{ $sectionTitle }}">Pago</h3>

            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Métodos de pago</span>
                <button type="button" wire:click="addPayment" class="inline-flex items-center gap-1 text-sm text-sky-700 hover:text-sky-800 dark:text-sky-400 dark:hover:text-sky-300 font-medium">
                    <x-heroicon-o-plus class="w-4 h-4" /> Agregar
                </button>
            </div>

            @if (count($payments) === 0)
                <p class="text-sm text-gray-400 dark:text-gray-500">Sin método de pago registrado todavía — la compra queda en cuenta corriente del proveedor.</p>
            @else
                <div class="space-y-3">
                    @foreach ($payments as $index => $payment)
                        <div wire:key="payment-{{ $index }}" class="space-y-1.5">
                            <div class="flex items-center gap-2">
                                <select wire:model="payments.{{ $index }}.method" class="flex-1 min-w-0 {{ $inputClass }}">
                                    @foreach ($paymentMethods as $method)
                                        <option value="{{ $method->value }}">{{ $method->label() }}</option>
                                    @endforeach
                                </select>
                                <button type="button" wire:click="removePayment({{ $index }})" class="shrink-0 text-gray-300 hover:text-red-500 dark:text-gray-600 dark:hover:text-red-400">
                                    <x-heroicon-o-trash class="w-4 h-4" />
                                </button>
                            </div>
                            <input type="number" min="0" step="0.01" wire:model.live="payments.{{ $index }}.amount" class="w-full {{ $inputClass }} text-right">
                        </div>
                    @endforeach
                </div>
            @endif
            <p class="text-xs {{ $this->remaining() > 0.005 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400 dark:text-gray-500' }}">
                Pagado: ${{ money($this->paidTotal()) }} de ${{ money($this->total()) }}
                @if ($this->remaining() > 0.005) · Resta ${{ money($this->remaining()) }} (queda en cuenta corriente) @endif
            </p>
            @error('payments') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        <div class="flex gap-3 {{ $sectionDivider }}">
            <button type="submit" wire:loading.attr="disabled" wire:target="save" class="flex-1 inline-flex items-center justify-center rounded-lg bg-sky-600 hover:bg-sky-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm disabled:opacity-50 transition-all">
                {{ $submitLabel }}
            </button>
            <a href="{{ route('purchases.index') }}" wire:navigate class="inline-flex items-center justify-center rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-5 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-all">
                Cancelar
            </a>
        </div>

        </div>
        {{-- /Columna derecha --}}
      </div>
      {{-- /grid --}}
    </div>
</form>
