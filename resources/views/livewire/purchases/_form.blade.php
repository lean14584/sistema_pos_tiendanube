@php
    $inputClass = 'w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent hover:border-gray-400 dark:hover:border-gray-600 transition-colors';
@endphp

<form wire:submit="save" class="space-y-6 max-w-3xl">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
            <x-provider-picker
                :provider-name="$selectedProviderName ?? '—'"
                :provider-query="$providerQuery"
                :provider-results="$this->providerResults"
            />
            @error('provider_id') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha de compra</label>
            <input type="date" wire:model="issue_date" class="{{ $inputClass }}">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha de vencimiento</label>
            <input type="date" wire:model="due_date" class="{{ $inputClass }}">
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipo de comprobante *</label>
            <select wire:model="tipo_comprobante" class="{{ $inputClass }}">
                @foreach ($tiposComprobante as $tipo)
                    <option value="{{ $tipo->value }}">{{ $tipo->label() }}</option>
                @endforeach
            </select>
            @error('tipo_comprobante') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Punto de venta *</label>
            <input type="number" min="1" max="9999" wire:model="punto_venta" class="{{ $inputClass }}">
            @error('punto_venta') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Número de comprobante *</label>
            <input type="number" min="1" max="99999999" wire:model="numero_comprobante" class="{{ $inputClass }}">
            @error('numero_comprobante') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
        </div>
    </div>
    <p class="text-xs text-gray-400 dark:text-gray-500 -mt-3">Datos del comprobante tal como lo emitió el proveedor (para el Libro IVA Compras), distintos del número interno de esta app.</p>

    <div>
        <div class="flex items-center justify-between mb-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Productos *</label>
        </div>

        <div class="relative">
            <x-heroicon-o-magnifying-glass class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input
                type="text"
                wire:model.live.debounce.200ms="productQuery"
                placeholder="Buscar producto por nombre o SKU..."
                class="{{ $inputClass }} pl-9"
            >
            @if (trim($productQuery) !== '')
                <div class="absolute z-20 mt-1 w-full bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 shadow-lg max-h-64 overflow-y-auto">
                    @forelse ($this->productResults as $product)
                        <button
                            type="button"
                            wire:click="addProductItem({{ $product->id }})"
                            class="w-full flex items-center justify-between gap-3 px-3 py-2 text-left hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
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

        @error('items') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror

        @if (count($items) > 0)
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden mt-3">
                <div class="overflow-x-auto">
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
                                    <input type="text" wire:model="items.{{ $index }}.description" class="w-full rounded-md border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                </td>
                                <td class="px-4 py-2">
                                    <input type="number" min="0.01" step="1" wire:model="items.{{ $index }}.quantity" class="w-full rounded-md border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                </td>
                                <td class="px-4 py-2">
                                    <input type="number" min="0" step="0.01" wire:model="items.{{ $index }}.unit_price" class="w-full rounded-md border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                </td>
                                <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300">
                                    ${{ money((float) $item['quantity'] * (float) $item['unit_price']) }}
                                </td>
                                <td class="px-2 py-2 text-center">
                                    <button type="button" wire:click="removeItem({{ $index }})" class="text-gray-400 hover:text-red-600 dark:text-gray-500 dark:hover:text-red-400">
                                        <x-heroicon-o-trash class="w-4 h-4" />
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            </div>
        @else
            <p class="text-sm text-gray-400 dark:text-gray-500 mt-2">Todavía no agregaste productos a esta compra.</p>
        @endif
        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1.5">Al guardar la compra, el stock de cada producto se incrementa automáticamente.</p>
    </div>

    {{-- Impuestos y percepciones (se cargan como vienen en la factura del proveedor) --}}
    <div>
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
                            class="flex-1 rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        >
                        <input
                            type="number" min="0" step="0.01"
                            wire:model.live="taxes.{{ $index }}.amount"
                            placeholder="Monto"
                            class="w-32 rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm text-right focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        >
                        <button type="button" wire:click="removeTax({{ $index }})" class="text-gray-400 hover:text-red-600 dark:text-gray-500 dark:hover:text-red-400">
                            <x-heroicon-o-trash class="w-4 h-4" />
                        </button>
                    </div>
                    @error("taxes.{$index}.concepto") <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                @endforeach
            </div>
        @endif
    </div>

    <div class="flex justify-end">
        <div class="w-full max-w-xs space-y-2 text-sm">
            <div class="flex justify-between text-gray-600 dark:text-gray-400">
                <span>Subtotal</span>
                <span>${{ money($this->subtotal()) }}</span>
            </div>
            <div class="flex justify-between items-center text-gray-600 dark:text-gray-400">
                <span>Impuesto (%)</span>
                <input type="number" min="0" step="0.01" wire:model.live="tax_rate" class="w-20 rounded-md border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-2 py-1 text-sm text-right focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>
            <div class="flex justify-between text-gray-600 dark:text-gray-400">
                <span>Monto impuesto</span>
                <span>${{ money($this->taxAmount()) }}</span>
            </div>
            @if ($this->percepcionesTotal() > 0)
                <div class="flex justify-between text-gray-600 dark:text-gray-400">
                    <span>Percepciones / otros impuestos</span>
                    <span>${{ money($this->percepcionesTotal()) }}</span>
                </div>
            @endif
            <div class="flex justify-between font-semibold text-gray-900 dark:text-gray-100 text-base pt-2 border-t border-gray-200 dark:border-gray-800">
                <span>Total</span>
                <span>${{ money($this->total()) }}</span>
            </div>
        </div>
    </div>

    <div>
        <div class="flex items-center justify-between mb-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Métodos de pago</label>
            <button type="button" wire:click="addPayment" class="inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium">
                <x-heroicon-o-plus class="w-3.5 h-3.5" />
                Agregar método de pago
            </button>
        </div>

        @if (count($payments) === 0)
            <p class="text-sm text-gray-400 dark:text-gray-500">Sin método de pago registrado todavía — la compra queda en cuenta corriente del proveedor.</p>
        @else
            <div class="space-y-2">
                @foreach ($payments as $index => $payment)
                    <div wire:key="payment-{{ $index }}" class="flex items-center gap-2">
                        <select wire:model="payments.{{ $index }}.method" class="rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                            @foreach ($paymentMethods as $method)
                                <option value="{{ $method->value }}">{{ $method->label() }}</option>
                            @endforeach
                        </select>
                        <input type="number" min="0" step="0.01" wire:model.live="payments.{{ $index }}.amount" class="w-32 rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm text-right focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        <button type="button" wire:click="removePayment({{ $index }})" class="text-gray-400 hover:text-red-600 dark:text-gray-500 dark:hover:text-red-400">
                            <x-heroicon-o-trash class="w-4 h-4" />
                        </button>
                    </div>
                @endforeach
            </div>
        @endif
        <p class="text-xs mt-2 {{ $this->remaining() > 0.005 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400 dark:text-gray-500' }}">
            Pagado: ${{ money($this->paidTotal()) }} de ${{ money($this->total()) }}
            @if ($this->remaining() > 0.005) · Resta ${{ money($this->remaining()) }} (queda en cuenta corriente) @endif
        </p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Estado</label>
            <select wire:model="status" class="{{ $inputClass }}">
                @foreach ($statuses as $s)
                    <option value="{{ $s->value }}">{{ $s->label() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notas</label>
        <textarea wire:model="notes" rows="3" placeholder="Condiciones de pago, remito, etc." class="{{ $inputClass }}"></textarea>
    </div>

    <div class="flex gap-3 pt-2">
        <button type="submit" class="rounded-lg bg-gradient-to-r from-indigo-600 to-indigo-500 px-4 py-2 text-sm font-medium text-white shadow-md shadow-indigo-600/30 hover:from-indigo-700 hover:to-indigo-600 hover:shadow-lg hover:shadow-indigo-600/40 active:scale-[0.98] transition-all">
            {{ $submitLabel }}
        </button>
        <a href="{{ route('purchases.index') }}" wire:navigate class="rounded-lg border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-800 hover:border-gray-400 dark:hover:border-gray-600 hover:shadow-md active:scale-[0.98] transition-all">
            Cancelar
        </a>
    </div>
</form>
