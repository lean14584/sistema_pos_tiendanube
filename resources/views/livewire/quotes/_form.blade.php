@php
    $inputClass = 'w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/60 text-gray-800 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-400 focus:border-sky-400 transition';
    $cellInputClass = 'w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 dark:text-gray-100 px-2 py-1.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-400 focus:border-sky-400 transition';
    $sectionTitle = 'text-xs font-bold text-sky-700 dark:text-sky-400 uppercase tracking-wider';
    $sectionDivider = 'border-t border-sky-200/70 dark:border-gray-800 pt-5 mt-5';
    $label = 'block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1';
@endphp

<form wire:submit="save" class="max-w-5xl">
    <div class="rounded-2xl border border-sky-200 dark:border-gray-800 bg-sky-100/70 dark:bg-gray-900 shadow-sm p-5 sm:p-7">
      <div class="grid grid-cols-1 lg:grid-cols-12 lg:gap-x-8">
        {{-- Columna izquierda: Ítems --}}
        <div class="lg:col-span-8">
        {{-- Sección: Ítems --}}
        <div class="space-y-3">
            <h3 class="{{ $sectionTitle }}">Ítems</h3>

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
                                <th class="px-4 py-2 font-medium w-20">Desc %</th>
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
                                        <input type="number" min="0" step="1" wire:model="items.{{ $index }}.quantity" class="{{ $cellInputClass }}">
                                    </td>
                                    <td class="px-4 py-2">
                                        <input type="number" min="0" step="0.01" wire:model="items.{{ $index }}.unit_price" class="{{ $cellInputClass }}">
                                    </td>
                                    <td class="px-4 py-2">
                                        <input type="number" min="0" max="100" step="0.01" wire:model.live="items.{{ $index }}.discount" class="{{ $cellInputClass }} text-right">
                                    </td>
                                    <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300">
                                        ${{ money((float) $item['quantity'] * (float) $item['unit_price'] * (1 - (float) ($item['discount'] ?? 0) / 100)) }}
                                    </td>
                                    <td class="px-2 py-2 text-center">
                                        <button type="button" wire:click="removeItem({{ $index }})" class="text-gray-300 hover:text-red-500 dark:text-gray-600 dark:hover:text-red-400">
                                            <x-heroicon-o-trash class="w-4 h-4" />
                                        </button>
                                    </td>
                                </tr>
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
                                        <input type="number" min="0" step="1" wire:model="items.{{ $index }}.quantity" class="{{ $cellInputClass }}">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-400 dark:text-gray-500 mb-0.5">Precio unit.</label>
                                        <input type="number" min="0" step="0.01" wire:model="items.{{ $index }}.unit_price" class="{{ $cellInputClass }}">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-400 dark:text-gray-500 mb-0.5">Desc %</label>
                                        <input type="number" min="0" max="100" step="0.01" wire:model.live="items.{{ $index }}.discount" class="{{ $cellInputClass }} text-right">
                                    </div>
                                </div>
                                <div class="flex justify-between text-sm font-medium text-gray-700 dark:text-gray-300 pt-1">
                                    <span>Total</span>
                                    <span>${{ money((float) $item['quantity'] * (float) $item['unit_price'] * (1 - (float) ($item['discount'] ?? 0) / 100)) }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <p class="text-sm text-gray-400 dark:text-gray-500">Buscá un producto arriba para agregarlo al presupuesto.</p>
            @endif

            <button type="button" wire:click="addFreeformItem" class="text-xs text-gray-400 hover:text-sky-700 dark:text-gray-500 dark:hover:text-sky-400 transition-colors">
                + Agregar ítem sin producto
            </button>

            {{-- Resumen de totales, pegado a la lista de ítems --}}
            <div class="flex justify-end">
                <div class="w-full max-w-xs space-y-1.5 text-sm rounded-xl border border-sky-300 dark:border-sky-500/30 bg-sky-100 dark:bg-sky-500/20 p-4">
                    <div class="flex justify-between text-gray-600 dark:text-gray-400">
                        <span>Subtotal</span>
                        <span>${{ money($this->subtotal()) }}</span>
                    </div>
                    <div class="flex justify-between items-center text-gray-600 dark:text-gray-400">
                        <span>Impuesto (%)</span>
                        <input type="number" min="0" step="0.01" wire:model.live="tax_rate" class="w-20 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-2 py-1 text-sm text-right text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:border-sky-400 transition">
                    </div>
                    <div class="flex justify-between text-gray-600 dark:text-gray-400">
                        <span>Monto impuesto</span>
                        <span>${{ money($this->taxAmount()) }}</span>
                    </div>
                    <div class="flex justify-between font-semibold text-sky-900 dark:text-sky-100 text-base pt-1.5 border-t border-sky-300/70 dark:border-sky-500/20">
                        <span>Total</span>
                        <span>${{ money($this->total()) }}</span>
                    </div>
                </div>
            </div>
        </div>
        </div>
        {{-- /Columna izquierda --}}

        {{-- Columna derecha: resto de los datos --}}
        <div class="lg:col-span-4 mt-6 pt-6 border-t lg:mt-0 lg:pt-0 lg:border-t-0 lg:border-l lg:pl-8 border-sky-200/70 dark:border-gray-800">

        {{-- Sección: Cliente y vigencia --}}
        <div class="space-y-4">
            <h3 class="{{ $sectionTitle }}">Cliente y vigencia</h3>

            <div>
                <x-client-picker
                    :client-name="$clients->firstWhere('id', (int) $client_id)?->name ?? '—'"
                    :client-query="$clientQuery"
                    :client-results="$this->clientResults"
                />
                @error('client_id') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>
            <x-select label="Lista de precios" wire:model.live="price_list_id">
                <option value="">Precio base</option>
                @foreach ($priceLists as $list)
                    <option value="{{ $list->id }}">{{ $list->name }} ({{ (float) $list->adjustment_percent > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($list->adjustment_percent, 2), '0'), '.') }}%)</option>
                @endforeach
            </x-select>

            <div>
                <label class="{{ $label }}">Fecha de emisión</label>
                <input type="date" wire:model="issue_date" class="{{ $inputClass }}">
            </div>
            <div>
                <label class="{{ $label }}">Válido hasta</label>
                <input type="date" wire:model="valid_until" class="{{ $inputClass }}">
            </div>
        </div>

        <div class="flex gap-3 {{ $sectionDivider }}">
            <button type="submit" wire:loading.attr="disabled" wire:target="save" class="flex-1 inline-flex items-center justify-center rounded-lg bg-sky-600 hover:bg-sky-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm disabled:opacity-50 transition-all">
                {{ $submitLabel }}
            </button>
            <a href="{{ route('quotes.index') }}" wire:navigate class="inline-flex items-center justify-center rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-5 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-all">
                Cancelar
            </a>
        </div>

        </div>
        {{-- /Columna derecha --}}
      </div>
      {{-- /grid --}}
    </div>
</form>
