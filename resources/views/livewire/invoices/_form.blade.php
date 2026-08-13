@php
    $inputClass = 'w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent hover:border-gray-400 dark:hover:border-gray-600 transition-colors';
@endphp

<form wire:submit="save" class="space-y-5 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm p-6">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-start">
        @if ($esNotaCredito)
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipo de comprobante</label>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ App\Enums\TipoComprobanteInterno::from($tipo_comprobante_interno)->label() }}
                    <span class="text-gray-400 dark:text-gray-500">— una Nota de Crédito no puede convertirse en otro tipo de comprobante.</span>
                </p>
            </div>
        @else
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipo de comprobante</label>
                <div class="flex w-full rounded-lg border border-gray-300 dark:border-gray-700 p-0.5 bg-gray-50 dark:bg-gray-900/50">
                    @foreach ($tipoComprobanteInternoOptions as $option)
                        <button
                            type="button"
                            wire:click="$set('tipo_comprobante_interno', '{{ $option->value }}')"
                            class="flex-1 px-3 py-1.5 rounded-md text-sm font-medium text-center transition-colors {{ $tipo_comprobante_interno === $option->value ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-200/60 dark:hover:bg-gray-800' }}"
                        >
                            {{ $option->label() }}
                        </button>
                    @endforeach
                </div>
                @error('tipo_comprobante_interno') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>
        @endif

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Estado</label>
            <select wire:model="status" class="{{ $inputClass }}">
                @foreach ($statuses as $s)
                    <option value="{{ $s->value }}">{{ $s->label() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cliente *</label>
            <select wire:model.live="client_id" required class="{{ $inputClass }}">
                <option value="">Seleccionar...</option>
                @foreach ($clients as $client)
                    <option value="{{ $client->id }}">{{ $client->name }}</option>
                @endforeach
            </select>
            @error('client_id') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Lista de precios</label>
            <select wire:model.live="price_list_id" class="{{ $inputClass }}">
                <option value="">Precio base</option>
                @foreach ($priceLists as $list)
                    <option value="{{ $list->id }}">{{ $list->name }} ({{ (float) $list->adjustment_percent > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($list->adjustment_percent, 2), '0'), '.') }}%)</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha de emisión</label>
            <input type="date" wire:model="issue_date" class="{{ $inputClass }}">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha de vencimiento</label>
            <input type="date" wire:model="due_date" class="{{ $inputClass }}">
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Ítems *</label>

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
                            class="w-full flex items-center justify-between gap-3 px-3 py-2 text-left hover:bg-indigo-50/70 dark:hover:bg-indigo-500/10 transition-colors"
                        >
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $product->name }}</span>
                                <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $product->sku ? "SKU: {$product->sku} · " : '' }}${{ number_format($product->price, 2) }}</span>
                            </span>
                            <x-heroicon-o-plus-circle class="w-5 h-5 text-indigo-500 shrink-0" />
                        </button>
                    @empty
                        <p class="p-3 text-sm text-gray-400 dark:text-gray-500">Sin resultados para "{{ $productQuery }}".</p>
                    @endforelse
                </div>
            @endif
        </div>

        @error('items') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror

        @if (count($items) > 0)
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden mt-2">
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr class="text-left text-gray-500 dark:text-gray-400">
                            <th class="px-3 py-1.5 font-medium">Descripción</th>
                            <th class="px-3 py-1.5 font-medium w-20">Cant.</th>
                            <th class="px-3 py-1.5 font-medium w-28">Precio unit.</th>
                            <th class="px-3 py-1.5 font-medium w-20">Desc %</th>
                            <th class="px-3 py-1.5 font-medium w-28">IVA</th>
                            <th class="px-3 py-1.5 font-medium w-28 text-right">Total</th>
                            <th class="px-2 py-1.5 w-10"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $index => $item)
                            <tr wire:key="item-{{ $index }}" class="border-t border-gray-100 dark:border-gray-800">
                                <td class="px-3 py-1">
                                    <input type="text" wire:model="items.{{ $index }}.description" class="w-full rounded-md border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                </td>
                                <td class="px-3 py-1">
                                    <input type="number" min="0" step="1" wire:model="items.{{ $index }}.quantity" class="w-full rounded-md border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                </td>
                                <td class="px-3 py-1">
                                    <input type="number" min="0" step="0.01" wire:model="items.{{ $index }}.unit_price" class="w-full rounded-md border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                </td>
                                <td class="px-3 py-1">
                                    <input type="number" min="0" max="100" step="0.01" wire:model.live="items.{{ $index }}.discount" class="w-full rounded-md border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-2 py-1 text-sm text-right focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                </td>
                                <td class="px-3 py-1">
                                    <select wire:model.live="items.{{ $index }}.iva_rate" class="w-full rounded-md border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                        @foreach (App\Enums\AlicuotaIva::cases() as $alicuota)
                                            <option value="{{ $alicuota->value }}">{{ $alicuota->label() }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-3 py-1 text-right text-gray-700 dark:text-gray-300">
                                    ${{ number_format((float) $item['quantity'] * (float) $item['unit_price'] * (1 - (float) ($item['discount'] ?? 0) / 100), 2) }}
                                </td>
                                <td class="px-2 py-1 text-center">
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
            <p class="text-sm text-gray-400 dark:text-gray-500 mt-2">Buscá un producto arriba para agregarlo a la factura.</p>
        @endif

        <button type="button" wire:click="addFreeformItem" class="mt-1.5 text-xs text-gray-400 hover:text-indigo-600 dark:text-gray-500 dark:hover:text-indigo-400 transition-colors">
            + Agregar ítem sin producto
        </button>
    </div>

    <div class="flex justify-end">
        <div class="w-full max-w-xs rounded-xl bg-gradient-to-br from-indigo-50 to-violet-50 dark:from-indigo-500/10 dark:to-violet-500/10 border border-indigo-100 dark:border-indigo-500/20 p-4 space-y-1.5 text-sm">
            <div class="flex justify-between text-gray-600 dark:text-gray-400">
                <span>Neto gravado</span>
                <span>${{ number_format($this->netoGravado(), 2) }}</span>
            </div>
            @if ($this->netoExento() > 0)
                <div class="flex justify-between text-gray-600 dark:text-gray-400">
                    <span>Exento / no gravado</span>
                    <span>${{ number_format($this->netoExento(), 2) }}</span>
                </div>
            @endif
            @foreach ($this->ivaBreakdown() as $linea)
                <div class="flex justify-between text-gray-600 dark:text-gray-400">
                    <span>IVA {{ rtrim(rtrim(number_format($linea['tasa'], 2), '0'), '.') }}%</span>
                    <span>${{ number_format($linea['iva'], 2) }}</span>
                </div>
            @endforeach
            <div class="flex items-end justify-between pt-2 border-t border-indigo-100 dark:border-indigo-500/20">
                <span class="text-sm font-medium text-gray-600 dark:text-gray-300">Total</span>
                <span class="text-2xl font-extrabold bg-gradient-to-r from-indigo-700 to-violet-700 dark:from-indigo-300 dark:to-violet-300 bg-clip-text text-transparent">${{ number_format($this->total(), 2) }}</span>
            </div>
        </div>
    </div>

    @if ($tipo_comprobante_interno !== 'remito_x')
        @php $esDevolucion = $tipo_comprobante_interno === 'devolucion'; @endphp
        <div>
            <div class="flex items-center justify-between mb-1.5">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ $esDevolucion ? 'Reintegro al cliente' : 'Métodos de pago' }}
                </label>
                <button type="button" wire:click="addPayment" class="inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium">
                    <x-heroicon-o-plus class="w-3.5 h-3.5" />
                    {{ $esDevolucion ? 'Agregar reintegro' : 'Agregar método de pago' }}
                </button>
            </div>

            @if (count($payments) === 0)
                <p class="text-sm text-gray-400 dark:text-gray-500">
                    {{ $esDevolucion ? 'Sin reintegro registrado todavía.' : 'Sin método de pago registrado todavía.' }}
                </p>
            @else
                <div class="space-y-1.5">
                    @foreach ($payments as $index => $payment)
                        <div wire:key="payment-{{ $index }}" class="flex items-center gap-2">
                            <select wire:model="payments.{{ $index }}.method" class="rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                @foreach ($paymentMethods as $method)
                                    <option value="{{ $method->value }}">{{ $method->label() }}</option>
                                @endforeach
                            </select>
                            <input type="number" min="0" step="0.01" wire:model.live="payments.{{ $index }}.amount" class="w-32 rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-1.5 text-sm text-right focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                            <button type="button" wire:click="removePayment({{ $index }})" class="text-gray-400 hover:text-red-600 dark:text-gray-500 dark:hover:text-red-400">
                                <x-heroicon-o-trash class="w-4 h-4" />
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif
            <p class="text-xs mt-2 {{ $this->remaining() > 0.005 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400 dark:text-gray-500' }}">
                {{ $esDevolucion ? 'Reintegrado' : 'Pagado' }}: ${{ number_format($this->paidTotal(), 2) }} de ${{ number_format($this->total(), 2) }}
                @if ($this->remaining() > 0.005) · Resta ${{ number_format($this->remaining(), 2) }} @endif
            </p>
        </div>
    @endif

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notas</label>
        <textarea wire:model="notes" rows="2" placeholder="Condiciones de pago, agradecimiento, etc." class="{{ $inputClass }}"></textarea>
    </div>

    @isset($printOnSave)
        <div class="flex items-center gap-2">
            <input type="checkbox" wire:model="printOnSave" id="printOnSave" class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 focus:ring-indigo-500 focus:ring-offset-0">
            <label for="printOnSave" class="text-sm text-gray-700 dark:text-gray-300">Imprimir ticket al guardar</label>
        </div>
    @endisset

    <div class="flex gap-3 pt-1">
        <button type="submit" class="rounded-lg bg-gradient-to-r from-indigo-600 to-indigo-500 px-4 py-2 text-sm font-medium text-white shadow-md shadow-indigo-600/30 hover:from-indigo-700 hover:to-indigo-600 hover:shadow-lg hover:shadow-indigo-600/40 active:scale-[0.98] transition-all">
            {{ $submitLabel }}
        </button>
        <a href="{{ route('invoices.index') }}" wire:navigate class="rounded-lg border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-800 hover:border-gray-400 dark:hover:border-gray-600 hover:shadow-md active:scale-[0.98] transition-all">
            Cancelar
        </a>
    </div>
</form>
