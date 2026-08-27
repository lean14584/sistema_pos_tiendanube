@php
    $inputClass = 'w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/60 text-gray-800 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 transition';
@endphp

<form wire:submit="save" class="space-y-5 rounded-2xl border border-indigo-100 dark:border-gray-800 bg-gradient-to-b from-white to-indigo-50/50 dark:from-gray-900 dark:to-gray-950 shadow-md shadow-indigo-100/50 dark:shadow-black/30 p-6">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cliente *</label>
            <p class="text-sm text-gray-800 dark:text-gray-200 mb-1">{{ $clients->firstWhere('id', (int) $client_id)?->name ?? '—' }}</p>
            <div class="relative">
                <x-heroicon-o-magnifying-glass class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
                <input
                    type="text"
                    wire:model.live.debounce.200ms="clientQuery"
                    placeholder="Buscar para cambiar de cliente..."
                    class="{{ $inputClass }} pl-9"
                >
                @if (trim($clientQuery) !== '')
                    <div class="absolute z-20 mt-1 w-full bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 shadow-lg max-h-64 overflow-y-auto">
                        @forelse ($this->clientResults as $client)
                            <button
                                type="button"
                                wire:click="selectClient({{ $client->id }})"
                                class="w-full flex items-center justify-between gap-3 px-3 py-2 text-left hover:bg-indigo-50/70 dark:hover:bg-indigo-500/10 transition-colors"
                            >
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $client->name }}</span>
                                    @if ($client->phone)
                                        <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $client->phone }}</span>
                                    @endif
                                </span>
                            </button>
                        @empty
                            <p class="p-3 text-sm text-gray-400 dark:text-gray-500">Sin resultados para "{{ $clientQuery }}".</p>
                        @endforelse
                    </div>
                @endif
            </div>
            @error('client_id') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
        </div>
        <x-select label="Lista de precios" wire:model.live="price_list_id">
            <option value="">Precio base</option>
            @foreach ($priceLists as $list)
                <option value="{{ $list->id }}">{{ $list->name }} ({{ (float) $list->adjustment_percent > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($list->adjustment_percent, 2), '0'), '.') }}%)</option>
            @endforeach
        </x-select>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha de emisión</label>
            <input type="date" wire:model="issue_date" class="{{ $inputClass }}">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Válido hasta</label>
            <input type="date" wire:model="valid_until" class="{{ $inputClass }}">
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Ítems *</label>

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
                                    {{ $product->sku ? "SKU: {$product->sku} · " : '' }}${{ number_format($product->price, 2) }}
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
                            <th class="px-4 py-2 font-medium w-20">Desc %</th>
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
                                    <input type="number" min="0" step="1" wire:model="items.{{ $index }}.quantity" class="w-full rounded-md border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                </td>
                                <td class="px-4 py-2">
                                    <input type="number" min="0" step="0.01" wire:model="items.{{ $index }}.unit_price" class="w-full rounded-md border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                </td>
                                <td class="px-4 py-2">
                                    <input type="number" min="0" max="100" step="0.01" wire:model.live="items.{{ $index }}.discount" class="w-full rounded-md border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-2 py-1.5 text-sm text-right focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                </td>
                                <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300">
                                    ${{ number_format((float) $item['quantity'] * (float) $item['unit_price'] * (1 - (float) ($item['discount'] ?? 0) / 100), 2) }}
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
            <p class="text-sm text-gray-400 dark:text-gray-500 mt-2">Buscá un producto arriba para agregarlo al presupuesto.</p>
        @endif

        <button type="button" wire:click="addFreeformItem" class="mt-2 text-xs text-gray-400 hover:text-indigo-600 dark:text-gray-500 dark:hover:text-indigo-400 transition-colors">
            + Agregar ítem sin producto
        </button>
    </div>

    <div class="flex justify-end">
        <div class="w-full max-w-xs rounded-2xl overflow-hidden border border-indigo-100 dark:border-indigo-500/20 shadow-sm">
            <div class="bg-white/70 dark:bg-gray-900/40 px-4 py-3 space-y-2 text-sm">
                <div class="flex justify-between text-gray-600 dark:text-gray-400">
                    <span>Subtotal</span>
                    <span>${{ number_format($this->subtotal(), 2) }}</span>
                </div>
                <div class="flex justify-between items-center text-gray-600 dark:text-gray-400">
                    <span>Impuesto (%)</span>
                    <input type="number" min="0" step="0.01" wire:model.live="tax_rate" class="w-20 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/60 px-2 py-1 text-sm text-right text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="flex justify-between text-gray-600 dark:text-gray-400">
                    <span>Monto impuesto</span>
                    <span>${{ number_format($this->taxAmount(), 2) }}</span>
                </div>
            </div>
            <div class="bg-gradient-to-br from-indigo-600 to-violet-700 px-4 py-3 flex items-end justify-between text-white">
                <span class="text-xs font-medium uppercase tracking-wide text-white/80">Total</span>
                <span class="text-2xl font-extrabold tracking-tight">${{ number_format($this->total(), 2) }}</span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <x-select label="Estado" wire:model="status">
            @foreach ($statuses as $s)
                <option value="{{ $s->value }}">{{ $s->label() }}</option>
            @endforeach
        </x-select>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notas</label>
        <textarea wire:model="notes" rows="3" placeholder="Condiciones, validez, aclaraciones, etc." class="{{ $inputClass }}"></textarea>
    </div>

    <div class="flex gap-3 pt-2">
        <button type="submit" class="rounded-lg bg-gradient-to-r from-indigo-600 to-indigo-500 px-4 py-2 text-sm font-medium text-white shadow-md shadow-indigo-600/30 hover:from-indigo-700 hover:to-indigo-600 hover:shadow-lg hover:shadow-indigo-600/40 active:scale-[0.98] transition-all">
            {{ $submitLabel }}
        </button>
        <a href="{{ route('quotes.index') }}" wire:navigate class="rounded-lg border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-800 hover:border-gray-400 dark:hover:border-gray-600 hover:shadow-md active:scale-[0.98] transition-all">
            Cancelar
        </a>
    </div>
</form>
