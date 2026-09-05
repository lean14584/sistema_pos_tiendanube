<div class="p-8 max-w-4xl mx-auto">
    <a href="{{ route('products.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 mb-6">
        <x-heroicon-o-arrow-left class="w-4 h-4" /> Productos
    </a>
    <x-page-header title="Promociones" subtitle="El POS aplica estas promos solo, según el producto y la cantidad." icon="gift">
        <x-slot:actions>
            <a href="{{ route('promotions.groups.index') }}" wire:navigate class="inline-flex items-center gap-1.5 rounded-lg bg-white/15 border border-white/25 px-3 py-2 text-sm font-medium text-white hover:bg-white/25 transition-all">
                <x-heroicon-o-user-group class="w-4 h-4" /> Promos por familia
            </a>
        </x-slot:actions>
    </x-page-header>

    @php $inputClass = 'w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent'; @endphp

    <form wire:submit="save" class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md p-5 mb-8">
        <h2 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-4">{{ $editingId ? 'Editar promoción' : 'Nueva promoción' }}</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <x-product-picker
                    :product-name="$selectedProductName ?? '—'"
                    :product-query="$productQuery"
                    :product-results="$this->productResults"
                />
                @error('product_id') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Tipo de promo *</label>
                <select wire:model.live="type" class="{{ $inputClass }}">
                    @foreach ($types as $t)
                        <option value="{{ $t->value }}">{{ $t->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4">
            @if ($type === 'nxm')
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
                <div class="flex items-end"><p class="text-xs text-gray-400 dark:text-gray-500 pb-2">Ej. 2 y 1 = 2x1 (la 2da gratis).</p></div>
            @elseif ($type === 'segunda')
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Descuento 2da unidad (%) *</label>
                    <input type="number" min="1" max="100" step="0.01" wire:model="percent" class="{{ $inputClass }}">
                    @error('percent') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="flex items-end sm:col-span-2"><p class="text-xs text-gray-400 dark:text-gray-500 pb-2">Cada 2da unidad se cobra con ese descuento.</p></div>
            @elseif ($type === 'cantidad')
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Desde (unidades) *</label>
                    <input type="number" min="2" wire:model="min_qty" class="{{ $inputClass }}">
                    @error('min_qty') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Descuento (%) *</label>
                    <input type="number" min="1" max="100" step="0.01" wire:model="percent" class="{{ $inputClass }}">
                    @error('percent') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="flex items-end"><p class="text-xs text-gray-400 dark:text-gray-500 pb-2">Descuento en toda la línea al llegar a esa cantidad.</p></div>
            @endif
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
            <button type="submit" class="rounded-lg bg-gradient-to-r from-indigo-600 to-indigo-500 px-4 py-2 text-sm font-medium text-white shadow-md hover:from-indigo-700 hover:to-indigo-600">{{ $editingId ? 'Guardar cambios' : 'Crear promoción' }}</button>
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
                    <th class="px-4 py-2.5 font-medium">Producto</th>
                    <th class="px-4 py-2.5 font-medium">Promo</th>
                    <th class="px-4 py-2.5 font-medium">Vigencia</th>
                    <th class="px-4 py-2.5 font-medium">Estado</th>
                    <th class="px-4 py-2.5 font-medium text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($promotions as $promo)
                    <tr class="border-t border-gray-100 dark:border-gray-800">
                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $promo->product->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                            <span class="inline-flex items-center rounded-full bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 px-2 py-0.5 text-xs font-semibold">{{ $promo->shortLabel() }}</span>
                            <span class="text-xs text-gray-400 dark:text-gray-500 ml-1">{{ $promo->type->label() }}</span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                            {{ $promo->starts_at || $promo->ends_at ? (($promo->starts_at?->format('d/m/Y') ?? '…').' – '.($promo->ends_at?->format('d/m/Y') ?? '…')) : 'Sin límite' }}
                        </td>
                        <td class="px-4 py-3">
                            <button wire:click="toggle({{ $promo->id }})" class="text-xs font-medium {{ $promo->active ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400 dark:text-gray-500' }}">
                                {{ $promo->active ? '● Activa' : '○ Inactiva' }}
                            </button>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button wire:click="edit({{ $promo->id }})" class="p-1.5 rounded-md text-gray-500 hover:bg-indigo-50 hover:text-indigo-600 dark:text-gray-400 dark:hover:bg-indigo-500/10 dark:hover:text-indigo-400">
                                <x-heroicon-o-pencil class="w-4 h-4" />
                            </button>
                            <button x-on:click="confirmThen('¿Eliminar esta promoción?', () => $wire.delete({{ $promo->id }}))" class="p-1.5 rounded-md text-gray-500 hover:bg-red-50 hover:text-red-600 dark:text-gray-400 dark:hover:bg-red-500/10 dark:hover:text-red-400">
                                <x-heroicon-o-trash class="w-4 h-4" />
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400 dark:text-gray-500">Todavía no cargaste promociones.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
