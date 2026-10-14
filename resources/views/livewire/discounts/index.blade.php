<div class="p-8 max-w-6xl mx-auto">
    <x-page-header title="Mis Descuentos" subtitle="Descuentos manuales que cualquier cajero puede elegir en el POS para una venta puntual." icon="receipt-percent" />

    @php
        $inputClass = 'w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/60 dark:text-gray-100 px-3 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-400 focus:border-sky-400 transition';
        $sectionTitle = 'text-xs font-bold text-sky-700 dark:text-sky-400 uppercase tracking-wider';
    @endphp

    @if ($canManage)
    <form wire:submit="save" class="rounded-2xl border border-sky-200 dark:border-gray-800 bg-sky-200/70 dark:bg-gray-900 shadow-sm p-5 sm:p-6 mb-8">
        <h2 class="{{ $sectionTitle }} mb-4">{{ $editingId ? 'Editar descuento' : 'Nuevo descuento' }}</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Nombre *</label>
                <input type="text" wire:model="name" placeholder="Ej. Día de la Madre" class="{{ $inputClass }}">
                @error('name') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Tipo *</label>
                <select wire:model.live="type" class="{{ $inputClass }}">
                    @foreach ($types as $t)
                        <option value="{{ $t->value }}">{{ $t->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4">
            <div>
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">
                    {{ $type === 'percent' ? 'Porcentaje (%) *' : 'Monto ($) *' }}
                </label>
                <input type="number" min="0.01" step="0.01" @if($type === 'percent') max="100" @endif wire:model="value" class="{{ $inputClass }}">
                @error('value') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Desde (fecha, opcional)</label>
                <input type="date" wire:model="starts_at" class="{{ $inputClass }}">
            </div>
            <div>
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Hasta (fecha, opcional)</label>
                <input type="date" wire:model="ends_at" class="{{ $inputClass }}">
                @error('ends_at') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 mt-4">
            <input type="checkbox" wire:model="active" class="rounded border-gray-300 dark:border-gray-600 text-sky-600 focus:ring-sky-500"> Activo
        </label>

        <div class="flex gap-3 mt-5">
            <button type="submit" wire:loading.attr="disabled" wire:target="save" class="rounded-lg bg-sky-600 hover:bg-sky-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm disabled:opacity-50 transition-all">{{ $editingId ? 'Guardar cambios' : 'Crear descuento' }}</button>
            @if ($editingId)
                <button type="button" wire:click="cancel" class="rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-5 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">Cancelar</button>
            @endif
        </div>
    </form>
    @endif

    <div class="mb-4">
        <div class="relative">
            <x-heroicon-o-magnifying-glass class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input
                type="text"
                wire:model.live.debounce.300ms="query"
                placeholder="Buscar por nombre..."
                class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder-gray-500 pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent hover:border-gray-400 dark:hover:border-gray-600 transition-colors"
            >
        </div>
    </div>

    <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
        <div class="hidden sm:block overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800/50">
                <tr class="text-left text-gray-500 dark:text-gray-400">
                    <th class="px-4 py-2.5 font-medium">Nombre</th>
                    <th class="px-4 py-2.5 font-medium">Descuento</th>
                    <th class="px-4 py-2.5 font-medium">Vigencia</th>
                    <th class="px-4 py-2.5 font-medium">Estado</th>
                    <th class="px-4 py-2.5 font-medium text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($discounts as $discount)
                    <tr wire:key="discount-{{ $discount->id }}" class="border-t border-gray-100 dark:border-gray-800">
                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $discount->name }}</td>
                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                            <span class="inline-flex items-center rounded-full bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 px-2 py-0.5 text-xs font-semibold">{{ $discount->shortLabel() }}</span>
                            <span class="text-xs text-gray-400 dark:text-gray-500 ml-1">{{ $discount->type->label() }}</span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                            {{ $discount->starts_at || $discount->ends_at ? (($discount->starts_at?->format('d/m/Y') ?? '…').' – '.($discount->ends_at?->format('d/m/Y') ?? '…')) : 'Sin límite' }}
                        </td>
                        <td class="px-4 py-3">
                            @if ($canManage)
                                <button wire:click="toggle({{ $discount->id }})" class="text-xs font-medium {{ $discount->active ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400 dark:text-gray-500' }}">
                                    {{ $discount->active ? '● Activo' : '○ Inactivo' }}
                                </button>
                            @else
                                <span class="text-xs font-medium {{ $discount->active ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400 dark:text-gray-500' }}">
                                    {{ $discount->active ? '● Activo' : '○ Inactivo' }}
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @if ($canManage)
                            <button wire:click="edit({{ $discount->id }})" class="p-1.5 rounded-md text-gray-500 hover:bg-sky-50 hover:text-sky-600 dark:text-gray-400 dark:hover:bg-sky-500/10 dark:hover:text-sky-400">
                                <x-heroicon-o-pencil class="w-4 h-4" />
                            </button>
                            @endif
                            @if (Auth::user()->puedeEliminar())
                                <button x-on:click="confirmThen('¿Eliminar este descuento?', () => $wire.delete({{ $discount->id }}))" wire:loading.attr="disabled" wire:target="delete" aria-label="Eliminar descuento" class="p-1.5 rounded-md text-gray-500 hover:bg-red-50 hover:text-red-600 dark:text-gray-400 dark:hover:bg-red-500/10 dark:hover:text-red-400 disabled:opacity-50">
                                    <x-heroicon-o-trash class="w-4 h-4" />
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400 dark:text-gray-500">{{ $hasAnyDiscounts ? 'Sin resultados para "'.$query.'".' : 'Todavía no cargaste descuentos.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>

        <div class="sm:hidden divide-y divide-gray-100 dark:divide-gray-800">
            @forelse ($discounts as $discount)
                <div wire:key="discount-card-{{ $discount->id }}" class="p-4">
                    <div class="flex items-start justify-between gap-3">
                        <p class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ $discount->name }}</p>
                        <div class="flex gap-1 shrink-0">
                            @if ($canManage)
                            <button wire:click="edit({{ $discount->id }})" class="p-1.5 rounded-md text-gray-500 hover:bg-sky-50 hover:text-sky-600 dark:text-gray-400 dark:hover:bg-sky-500/10 dark:hover:text-sky-400">
                                <x-heroicon-o-pencil class="w-4 h-4" />
                            </button>
                            @endif
                            @if (Auth::user()->puedeEliminar())
                                <button x-on:click="confirmThen('¿Eliminar este descuento?', () => $wire.delete({{ $discount->id }}))" wire:loading.attr="disabled" wire:target="delete" aria-label="Eliminar descuento" class="p-1.5 rounded-md text-gray-500 hover:bg-red-50 hover:text-red-600 dark:text-gray-400 dark:hover:bg-red-500/10 dark:hover:text-red-400 disabled:opacity-50">
                                    <x-heroicon-o-trash class="w-4 h-4" />
                                </button>
                            @endif
                        </div>
                    </div>
                    <div class="mt-1">
                        <span class="inline-flex items-center rounded-full bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 px-2 py-0.5 text-xs font-semibold">{{ $discount->shortLabel() }}</span>
                        <span class="text-xs text-gray-400 dark:text-gray-500 ml-1">{{ $discount->type->label() }}</span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ $discount->starts_at || $discount->ends_at ? (($discount->starts_at?->format('d/m/Y') ?? '…').' – '.($discount->ends_at?->format('d/m/Y') ?? '…')) : 'Sin límite' }}
                    </p>
                    <button wire:click="toggle({{ $discount->id }})" class="text-xs font-medium mt-1 {{ $discount->active ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400 dark:text-gray-500' }}">
                        {{ $discount->active ? '● Activo' : '○ Inactivo' }}
                    </button>
                </div>
            @empty
                <div class="p-10 text-center text-gray-400 dark:text-gray-500">{{ $hasAnyDiscounts ? 'Sin resultados para "'.$query.'".' : 'Todavía no cargaste descuentos.' }}</div>
            @endforelse
        </div>
        <div class="p-4">
            {{ $discounts->links() }}
        </div>
    </div>
</div>
