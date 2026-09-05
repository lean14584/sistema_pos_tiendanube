@php
    $inputClass = 'w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/60 text-gray-800 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 transition';
@endphp

<form wire:submit="save" class="space-y-5 rounded-2xl border border-indigo-100 dark:border-gray-800 bg-gradient-to-b from-white to-indigo-50/50 dark:from-gray-900 dark:to-gray-950 shadow-md shadow-indigo-100/50 dark:shadow-black/30 p-6">
    {{-- Datos en dos filas (3 columnas) --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        {{-- Tipo de comprobante --}}
        @if ($esNotaCredito)
            <div>
                <x-select label="Tipo de comprobante" disabled title="Una Nota de Crédito no puede convertirse en otro tipo de comprobante.">
                    <option>{{ App\Enums\TipoComprobanteInterno::from($tipo_comprobante_interno)->label() }}</option>
                </x-select>
            </div>
        @else
            <div>
                <x-select label="Tipo de comprobante" wire:model.live="tipo_comprobante_interno">
                    @foreach ($tipoComprobanteInternoOptions as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </x-select>
                @error('tipo_comprobante_interno') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>
        @endif

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
        <x-select label="Estado" wire:model="status">
            @foreach ($statuses as $s)
                <option value="{{ $s->value }}">{{ $s->label() }}</option>
            @endforeach
        </x-select>
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
                                <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $product->sku ? "SKU: {$product->sku} · " : '' }}${{ money($product->price) }}</span>
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
                                    ${{ money((float) $item['quantity'] * (float) $item['unit_price'] * (1 - (float) ($item['discount'] ?? 0) / 100)) }}
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
        <div class="w-full max-w-xs rounded-2xl overflow-hidden border border-indigo-100 dark:border-indigo-500/20 shadow-sm">
            <div class="bg-white/70 dark:bg-gray-900/40 px-4 py-3 space-y-1.5 text-sm">
                <div class="flex justify-between text-gray-600 dark:text-gray-400">
                    <span>Neto gravado</span>
                    <span>${{ money($this->netoGravado()) }}</span>
                </div>
                @if ($this->netoExento() > 0)
                    <div class="flex justify-between text-gray-600 dark:text-gray-400">
                        <span>Exento / no gravado</span>
                        <span>${{ money($this->netoExento()) }}</span>
                    </div>
                @endif
                @foreach ($this->ivaBreakdown() as $linea)
                    <div class="flex justify-between text-gray-600 dark:text-gray-400">
                        <span>IVA {{ rtrim(rtrim(number_format($linea['tasa'], 2), '0'), '.') }}%</span>
                        <span>${{ money($linea['iva']) }}</span>
                    </div>
                @endforeach
            </div>
            <div class="bg-gradient-to-br from-indigo-600 to-violet-700 px-4 py-3 flex items-end justify-between text-white">
                <span class="text-xs font-medium uppercase tracking-wide text-white/80">Total</span>
                <span class="text-2xl font-extrabold tracking-tight">${{ money($this->total()) }}</span>
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
                {{ $esDevolucion ? 'Reintegrado' : 'Pagado' }}: ${{ money($this->paidTotal()) }} de ${{ money($this->total()) }}
                @if ($this->remaining() > 0.005) · Resta ${{ money($this->remaining()) }} @endif
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
