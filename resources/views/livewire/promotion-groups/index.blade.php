<div class="p-8 max-w-4xl mx-auto">
    <a href="{{ route('promotions.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 mb-6">
        <x-heroicon-o-arrow-left class="w-4 h-4" /> Promociones por producto
    </a>
    <x-page-header title="Promos por familia" subtitle="Agrupá productos (ej. Coca, Fanta, Sprite) y aplicá un NxM: el POS regala la unidad más barata del grupo." icon="gift" />

    @php $inputClass = 'w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent'; @endphp

    <form wire:submit="save" class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md p-5 mb-8">
        <h2 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-4">{{ $editingId ? 'Editar familia' : 'Nueva familia' }}</h2>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="sm:col-span-3">
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Nombre de la familia *</label>
                <input type="text" wire:model="name" placeholder="Ej. Gaseosas línea" class="{{ $inputClass }}">
                @error('name') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Llevás (N) *</label>
                <input type="number" min="2" wire:model="buy_qty" class="{{ $inputClass }}">
                @error('buy_qty') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Pagás (M) *</label>
                <input type="number" min="1" wire:model="pay_qty" class="{{ $inputClass }}">
                @error('pay_qty') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="flex items-end"><p class="text-xs text-gray-400 dark:text-gray-500 pb-2">Ej. 3 y 2 = llevás 3, pagás 2 (la más barata gratis).</p></div>
        </div>

        {{-- Productos de la familia --}}
        <div class="mt-4">
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Productos de la familia *</label>
            <div class="relative max-w-md">
                <x-heroicon-o-magnifying-glass class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
                <input type="text" wire:model.live.debounce.200ms="productQuery" placeholder="Buscar producto por nombre o SKU..." class="{{ $inputClass }} pl-9">
                @if (trim($productQuery) !== '')
                    <div class="absolute z-20 mt-1 w-full bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 shadow-lg max-h-56 overflow-y-auto">
                        @forelse ($this->productResults as $product)
                            <button type="button" wire:click="addProduct({{ $product->id }})" class="w-full flex items-center justify-between gap-3 px-3 py-2 text-left hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <span class="text-sm text-gray-900 dark:text-gray-100 truncate">{{ $product->name }}</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400 shrink-0">${{ money($product->price) }}</span>
                            </button>
                        @empty
                            <p class="p-3 text-sm text-gray-400 dark:text-gray-500">Sin resultados.</p>
                        @endforelse
                    </div>
                @endif
            </div>

            @if (count($selected) > 0)
                <div class="flex flex-wrap gap-2 mt-3">
                    @foreach ($selected as $id => $prod)
                        <span class="inline-flex items-center gap-2 rounded-full bg-gray-100 dark:bg-gray-800 pl-3 pr-1.5 py-1 text-sm text-gray-700 dark:text-gray-200">
                            {{ $prod['name'] }}
                            <button type="button" wire:click="removeProduct({{ $id }})" class="text-gray-400 hover:text-red-500">✕</button>
                        </span>
                    @endforeach
                </div>
            @endif
            @error('selected') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4">
            <div>
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Desde (fecha, opcional)</label>
                <input type="date" wire:model="starts_at" class="{{ $inputClass }}">
            </div>
            <div>
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Hasta (fecha, opcional)</label>
                <input type="date" wire:model="ends_at" class="{{ $inputClass }}">
                @error('ends_at') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>
            <label class="flex items-end gap-2 text-sm text-gray-700 dark:text-gray-300 pb-2">
                <input type="checkbox" wire:model="active" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500"> Activa
            </label>
        </div>

        <div class="flex gap-3 mt-5">
            <button type="submit" class="rounded-lg bg-gradient-to-r from-indigo-600 to-indigo-500 px-4 py-2 text-sm font-medium text-white shadow-md hover:from-indigo-700 hover:to-indigo-600">{{ $editingId ? 'Guardar cambios' : 'Crear familia' }}</button>
            @if ($editingId)
                <button type="button" wire:click="cancel" class="rounded-lg border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">Cancelar</button>
            @endif
        </div>
    </form>

    <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800/50">
                <tr class="text-left text-gray-500 dark:text-gray-400">
                    <th class="px-4 py-2.5 font-medium">Familia</th>
                    <th class="px-4 py-2.5 font-medium">Promo</th>
                    <th class="px-4 py-2.5 font-medium">Productos</th>
                    <th class="px-4 py-2.5 font-medium">Estado</th>
                    <th class="px-4 py-2.5 font-medium text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($groups as $group)
                    <tr class="border-t border-gray-100 dark:border-gray-800">
                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $group->name }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 px-2 py-0.5 text-xs font-semibold">{{ $group->shortLabel() }}</span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">{{ $group->products->pluck('name')->join(', ') }}</td>
                        <td class="px-4 py-3">
                            <button wire:click="toggle({{ $group->id }})" class="text-xs font-medium {{ $group->active ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400 dark:text-gray-500' }}">
                                {{ $group->active ? '● Activa' : '○ Inactiva' }}
                            </button>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button wire:click="edit({{ $group->id }})" class="p-1.5 rounded-md text-gray-500 hover:bg-indigo-50 hover:text-indigo-600 dark:text-gray-400 dark:hover:bg-indigo-500/10 dark:hover:text-indigo-400">
                                <x-heroicon-o-pencil class="w-4 h-4" />
                            </button>
                            <button x-on:click="confirmThen('¿Eliminar esta familia?', () => $wire.delete({{ $group->id }}))" class="p-1.5 rounded-md text-gray-500 hover:bg-red-50 hover:text-red-600 dark:text-gray-400 dark:hover:bg-red-500/10 dark:hover:text-red-400">
                                <x-heroicon-o-trash class="w-4 h-4" />
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400 dark:text-gray-500">Todavía no cargaste familias.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
