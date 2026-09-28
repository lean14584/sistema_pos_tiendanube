@php
    $inputClass = 'w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/60 dark:text-gray-100 px-3 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-400 focus:border-sky-400 transition';
    $sectionTitle = 'text-xs font-bold text-sky-700 dark:text-sky-400 uppercase tracking-wider';
@endphp

<div class="p-8 max-w-6xl mx-auto">
    <x-page-header title="Listas de precios" subtitle="Cada lista ajusta el precio base por un porcentaje (ej.: Mayorista −15%, Tarjeta +10%). Se asigna por cliente y se puede elegir al vender." icon="currency-dollar" />

    {{-- Alta / edición --}}
    <form wire:submit="save" class="rounded-2xl border border-sky-100 dark:border-gray-800 bg-sky-50/50 dark:bg-gray-900 shadow-sm p-5 sm:p-6 mb-6">
        <h3 class="{{ $sectionTitle }} mb-3">{{ $editingId ? 'Editar lista' : 'Nueva lista' }}</h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="sm:col-span-1">
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Nombre</label>
                <input type="text" wire:model="name" placeholder="Ej.: Mayorista" class="{{ $inputClass }}">
                @error('name') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Ajuste %</label>
                <input type="number" step="0.01" wire:model="adjustment_percent" class="{{ $inputClass }} text-right">
                @error('adjustment_percent') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="flex items-end">
                <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 pb-2.5">
                    <input type="checkbox" wire:model="active" class="rounded border-gray-300 dark:border-gray-700 text-sky-600 focus:ring-sky-500">
                    Activa
                </label>
            </div>
        </div>
        <div class="flex items-center gap-2 mt-4">
            <button type="submit" wire:loading.attr="disabled" wire:target="save" class="rounded-lg bg-sky-600 hover:bg-sky-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm disabled:opacity-50 transition-all">
                {{ $editingId ? 'Guardar cambios' : 'Agregar lista' }}
            </button>
            @if ($editingId)
                <button type="button" wire:click="cancel" class="rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-5 py-2.5 text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">Cancelar</button>
            @endif
        </div>
    </form>

    {{-- Listado --}}
    <div class="border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50">
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
                                <span class="ml-2 inline-flex items-center rounded-full bg-sky-50 dark:bg-sky-500/10 text-sky-700 dark:text-sky-400 px-2 py-0.5 text-xs font-medium">Predeterminada</span>
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
                                <button wire:click="makeDefault({{ $list->id }})" class="text-xs text-sky-700 hover:text-sky-800 dark:text-sky-400 mr-3">Hacer predeterminada</button>
                            @endunless
                            <button wire:click="edit({{ $list->id }})" class="text-xs text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-gray-100 mr-3">Editar</button>
                            @if (! $list->is_default && Auth::user()->puedeEliminar())
                                <button x-on:click="confirmThen('¿Eliminar esta lista de precios?', () => $wire.delete({{ $list->id }}))" class="text-xs text-red-600 hover:text-red-700 dark:text-red-400">Eliminar</button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </div>
</div>
