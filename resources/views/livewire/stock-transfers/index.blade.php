<div class="p-8 max-w-6xl mx-auto">
    <x-page-header title="Envío de Mercadería" subtitle="Trasladá stock entre sucursales como una sola operación" icon="arrows-right-left" />

    @php
        $inputClass = 'w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/60 dark:text-gray-100 px-3 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-400 focus:border-sky-400 transition';
        $cellInputClass = 'w-24 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 dark:text-gray-100 px-2 py-1 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-400 focus:border-sky-400 transition';
        $sectionTitle = 'text-xs font-bold text-sky-700 dark:text-sky-400 uppercase tracking-wider';
        $sectionDivider = 'border-t border-sky-200/70 dark:border-gray-800 pt-5 mt-5';
        $label = 'block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1';
    @endphp

    @if ($sucursales->count() < 2)
        <div class="rounded-2xl border border-sky-100 dark:border-gray-800 bg-sky-50/50 dark:bg-gray-900 shadow-sm p-8 text-center text-gray-400 dark:text-gray-500 mb-8">
            Necesitás al menos 2 sucursales activas para hacer un envío de mercadería.
        </div>
    @else
        <form wire:submit="save" class="mb-8">
            <div class="rounded-2xl border border-sky-100 dark:border-gray-800 bg-sky-50/50 dark:bg-gray-900 shadow-sm p-5 sm:p-7">

                {{-- Sección: Productos --}}
                <div class="space-y-3">
                    <h3 class="{{ $sectionTitle }}">Productos ({{ count($items) }})</h3>

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
                                            <span class="block text-xs text-gray-400 dark:text-gray-500">{{ $product->sku ?: '—' }} · Stock: {{ $product->stockEnSucursal((int) $from_sucursal_id) }}</span>
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
                            <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-800/50">
                                    <tr class="text-left text-gray-500 dark:text-gray-400">
                                        <th class="px-3 py-2 font-medium">Producto</th>
                                        <th class="px-3 py-2 font-medium w-28">Cantidad</th>
                                        <th class="px-2 py-2 w-10"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($items as $index => $item)
                                        <tr wire:key="item-{{ $index }}" class="border-t border-gray-100 dark:border-gray-800">
                                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $item['description'] }}</td>
                                            <td class="px-3 py-2">
                                                <input type="number" min="1" wire:model="items.{{ $index }}.quantity" class="{{ $cellInputClass }}">
                                                @error("items.{$index}.quantity") <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
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
                        </div>
                    @else
                        <p class="text-sm text-gray-400 dark:text-gray-500">Todavía no agregaste productos.</p>
                    @endif
                </div>

                {{-- Sección: Origen y destino --}}
                <div class="space-y-3 {{ $sectionDivider }}">
                    <h3 class="{{ $sectionTitle }}">Origen y destino</h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="{{ $label }}">Origen</label>
                            @if ($puedeElegirOrigen)
                                <select wire:model="from_sucursal_id" class="{{ $inputClass }}">
                                    @foreach ($sucursales as $s)
                                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                                    @endforeach
                                </select>
                            @else
                                <p class="px-3 py-2.5 text-sm text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800/60 rounded-xl border border-gray-200 dark:border-gray-700">{{ $sucursalActiva?->name }}</p>
                            @endif
                            @error('from_sucursal_id') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $label }}">Destino *</label>
                            <select wire:model="to_sucursal_id" class="{{ $inputClass }}">
                                <option value="">Elegir sucursal...</option>
                                @foreach ($sucursales as $s)
                                    @if ((string) $s->id !== $from_sucursal_id)
                                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                            @error('to_sucursal_id') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                {{-- Sección: Notas --}}
                <div class="space-y-3 {{ $sectionDivider }}">
                    <h3 class="{{ $sectionTitle }}">Notas</h3>
                    <textarea wire:model="notes" rows="2" class="{{ $inputClass }}" placeholder="Ej: reposición de fin de semana"></textarea>
                </div>

                <div class="{{ $sectionDivider }}">
                    <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-sky-600 hover:bg-sky-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm disabled:opacity-50 transition-all">
                        Registrar envío
                    </button>
                </div>
            </div>
        </form>
    @endif

    <div class="border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
        <div class="hidden sm:block overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800/50">
                <tr class="text-left text-gray-500 dark:text-gray-400">
                    <th class="px-4 py-2.5 font-medium">Fecha</th>
                    <th class="px-4 py-2.5 font-medium">De</th>
                    <th class="px-4 py-2.5 font-medium">A</th>
                    <th class="px-4 py-2.5 font-medium">Productos</th>
                    <th class="px-4 py-2.5 font-medium">Estado</th>
                    <th class="px-4 py-2.5 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($transfers as $transfer)
                    <tr class="border-t border-gray-100 dark:border-gray-800">
                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $transfer->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $transfer->fromSucursal->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $transfer->toSucursal->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">
                            {{ $transfer->items->map(fn ($i) => ($i->product->name ?? 'Producto eliminado').' x'.$i->quantity)->implode(', ') }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $transfer->status->colorClasses() }}">
                                {{ $transfer->status->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('stock-transfers.show', $transfer) }}" wire:navigate class="text-sky-700 hover:text-sky-800 dark:text-sky-400 dark:hover:text-sky-300 text-sm font-medium">
                                Ver
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400 dark:text-gray-500">Todavía no se registraron envíos entre sucursales.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>

        <div class="sm:hidden divide-y divide-gray-100 dark:divide-gray-800">
            @forelse ($transfers as $transfer)
                <div class="p-4">
                    <div class="flex items-start justify-between gap-3">
                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ $transfer->fromSucursal->name ?? '—' }} → {{ $transfer->toSucursal->name ?? '—' }}</p>
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset shrink-0 {{ $transfer->status->colorClasses() }}">
                            {{ $transfer->status->label() }}
                        </span>
                    </div>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ $transfer->created_at->format('d/m/Y H:i') }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        {{ $transfer->items->map(fn ($i) => ($i->product->name ?? 'Producto eliminado').' x'.$i->quantity)->implode(', ') }}
                    </p>
                    <a href="{{ route('stock-transfers.show', $transfer) }}" wire:navigate class="inline-block mt-2 text-sky-700 hover:text-sky-800 dark:text-sky-400 dark:hover:text-sky-300 text-sm font-medium">
                        Ver
                    </a>
                </div>
            @empty
                <div class="p-10 text-center text-gray-400 dark:text-gray-500">Todavía no se registraron envíos entre sucursales.</div>
            @endforelse
        </div>

        <div class="p-4 border-t border-gray-100 dark:border-gray-800">
            {{ $transfers->links() }}
        </div>
    </div>
</div>
