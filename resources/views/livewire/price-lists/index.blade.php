<div class="p-8 max-w-4xl mx-auto">
    <x-page-header title="Listas de precios" subtitle="Cada lista ajusta el precio base por un porcentaje (ej.: Mayorista −15%, Tarjeta +10%). Se asigna por cliente y se puede elegir al vender." icon="currency-dollar" />

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 px-4 py-3 text-sm">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-500/10 text-red-700 dark:text-red-400 px-4 py-3 text-sm">{{ session('error') }}</div>
    @endif

    {{-- Alta / edición --}}
    <form wire:submit="save" class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 p-4 mb-6">
        <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">{{ $editingId ? 'Editar lista' : 'Nueva lista' }}</h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="sm:col-span-1">
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Nombre</label>
                <input type="text" wire:model="name" placeholder="Ej.: Mayorista" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                @error('name') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Ajuste %</label>
                <input type="number" step="0.01" wire:model="adjustment_percent" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm text-right focus:outline-none focus:ring-2 focus:ring-indigo-500">
                @error('adjustment_percent') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="flex items-end">
                <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 pb-2">
                    <input type="checkbox" wire:model="active" class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 focus:ring-indigo-500">
                    Activa
                </label>
            </div>
        </div>
        <div class="flex items-center gap-2 mt-3">
            <button type="submit" class="rounded-lg bg-gradient-to-r from-indigo-600 to-indigo-500 px-4 py-2 text-sm font-medium text-white shadow-md shadow-indigo-600/30 hover:from-indigo-700 hover:to-indigo-600 active:scale-[0.98] transition-all">
                {{ $editingId ? 'Guardar cambios' : 'Agregar lista' }}
            </button>
            @if ($editingId)
                <button type="button" wire:click="cancel" class="rounded-lg border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800">Cancelar</button>
            @endif
        </div>
    </form>

    {{-- Listado --}}
    <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-800 bg-gray-100/80 dark:bg-gray-800/40">
                    <th class="px-5 py-3 font-medium">Nombre</th>
                    <th class="px-5 py-3 font-medium text-right">Ajuste</th>
                    <th class="px-5 py-3 font-medium text-center">Clientes</th>
                    <th class="px-5 py-3 font-medium text-center">Estado</th>
                    <th class="px-5 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($priceLists as $list)
                    <tr wire:key="pl-{{ $list->id }}" class="border-b border-gray-50 dark:border-gray-800/60 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                        <td class="px-5 py-3 text-gray-900 dark:text-gray-100 font-medium">
                            {{ $list->name }}
                            @if ($list->is_default)
                                <span class="ml-2 inline-flex items-center rounded-full bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 px-2 py-0.5 text-xs font-medium">Predeterminada</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right text-gray-700 dark:text-gray-300">{{ (float) $list->adjustment_percent > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($list->adjustment_percent, 2), '0'), '.') }}%</td>
                        <td class="px-5 py-3 text-center text-gray-500 dark:text-gray-400">{{ $list->clients_count }}</td>
                        <td class="px-5 py-3 text-center">
                            @if ($list->active)
                                <span class="text-emerald-600 dark:text-emerald-400">Activa</span>
                            @else
                                <span class="text-gray-400 dark:text-gray-500">Inactiva</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right whitespace-nowrap">
                            @unless ($list->is_default)
                                <button wire:click="makeDefault({{ $list->id }})" class="text-xs text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 mr-3">Hacer predeterminada</button>
                            @endunless
                            <button wire:click="edit({{ $list->id }})" class="text-xs text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-gray-100 mr-3">Editar</button>
                            @unless ($list->is_default)
                                <button wire:click="delete({{ $list->id }})" wire:confirm="¿Eliminar esta lista de precios?" class="text-xs text-red-600 hover:text-red-700 dark:text-red-400">Eliminar</button>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
