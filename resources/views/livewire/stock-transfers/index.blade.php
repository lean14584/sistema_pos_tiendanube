<div class="p-8 max-w-6xl mx-auto">
    <x-page-header title="Envío de Mercadería" subtitle="Trasladá stock entre sucursales como una sola operación" icon="arrows-right-left" />

    @php $inputClass = 'w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60 dark:text-gray-100 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 focus:bg-white dark:focus:bg-gray-800 transition'; @endphp
    @php $cellInputClass = 'w-24 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 dark:text-gray-100 px-2 py-1 text-sm shadow-sm hover:border-indigo-300 dark:hover:border-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 transition'; @endphp

    @if ($sucursales->count() < 2)
        <div class="bg-gradient-to-b from-white to-sky-50/70 dark:from-gray-900 dark:to-gray-950 rounded-2xl border border-sky-100 dark:border-gray-800 shadow-md shadow-sky-100/50 dark:shadow-black/30 p-8 text-center text-gray-400 dark:text-gray-500 mb-8">
            Necesitás al menos 2 sucursales activas para hacer un envío de mercadería.
        </div>
    @else
        <form wire:submit="save" class="mb-8">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-start">
                {{-- Columna izquierda: buscador de productos + lista --}}
                <div class="lg:col-span-8 order-2 lg:order-1 space-y-3">
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
                                            <span class="block text-xs text-gray-400 dark:text-gray-500">{{ $product->sku ?: '—' }} · Stock: {{ $product->stockEnSucursal((int) $from_sucursal_id) }}</span>
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
                        @else
                            <div class="flex flex-col items-center justify-center h-40 text-center text-gray-400 dark:text-gray-500 px-4">
                                <x-heroicon-o-magnifying-glass class="w-8 h-8 text-gray-300 dark:text-gray-700 mb-2" />
                                <p class="text-sm font-medium">Todavía no agregaste productos</p>
                                <p class="text-xs mt-0.5">Buscá arriba por nombre o SKU para agregarlos.</p>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Columna derecha: card celeste con todos los datos --}}
                <div class="lg:col-span-4 order-1 lg:order-2">
                    <div class="lg:sticky lg:top-4 rounded-2xl border border-sky-100 dark:border-gray-800 bg-gradient-to-b from-white to-sky-50/70 dark:from-gray-900 dark:to-gray-950 shadow-md shadow-sky-100/50 dark:shadow-black/30 p-4 space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Origen</label>
                            @if ($puedeElegirOrigen)
                                <select wire:model="from_sucursal_id" class="{{ $inputClass }}">
                                    @foreach ($sucursales as $s)
                                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                                    @endforeach
                                </select>
                            @else
                                <p class="px-3 py-2.5 text-sm text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800/60 rounded-xl border border-gray-200 dark:border-gray-700">{{ $sucursalActiva?->name }}</p>
                            @endif
                            @error('from_sucursal_id') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Destino *</label>
                            <select wire:model="to_sucursal_id" class="{{ $inputClass }}">
                                <option value="">Elegir sucursal...</option>
                                @foreach ($sucursales as $s)
                                    @if ((string) $s->id !== $from_sucursal_id)
                                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                            @error('to_sucursal_id') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Notas (opcional)</label>
                            <textarea wire:model="notes" rows="2" class="{{ $inputClass }}" placeholder="Ej: reposición de fin de semana"></textarea>
                        </div>

                        <button type="submit" wire:loading.attr="disabled" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-indigo-600 to-indigo-500 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-600/30 hover:from-indigo-700 hover:to-indigo-600 active:scale-[0.99] disabled:opacity-50 transition-all">
                            <x-heroicon-o-arrows-right-left class="w-4 h-4" />
                            Registrar envío
                        </button>
                    </div>
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
                            <a href="{{ route('stock-transfers.show', $transfer) }}" wire:navigate class="text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300 text-sm font-medium">
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
                    <a href="{{ route('stock-transfers.show', $transfer) }}" wire:navigate class="inline-block mt-2 text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300 text-sm font-medium">
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
