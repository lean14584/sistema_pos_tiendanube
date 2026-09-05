<div class="p-8 max-w-5xl mx-auto">
    <x-page-header title="Sucursales" subtitle="Locales de la empresa, cada uno con su punto de venta AFIP" icon="building-storefront">
        <x-slot:actions>
            <a href="{{ route('sucursales.create') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-emerald-900/20 hover:bg-emerald-400 active:scale-[0.98] transition-all">
                <x-heroicon-o-plus class="w-4 h-4" /> Nueva sucursal
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40">
        @if ($sucursales->isEmpty())
            <div class="p-12 text-center text-gray-400 dark:text-gray-500">
                <x-heroicon-o-building-storefront class="w-10 h-10 mx-auto mb-3 text-gray-300 dark:text-gray-700" />
                <p class="text-sm">Todavía no agregaste sucursales.</p>
                <a href="{{ route('sucursales.create') }}" wire:navigate class="text-sm text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300">
                    Agregar la primera
                </a>
            </div>
        @else
            <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-800 bg-gray-100/80 dark:bg-gray-800/40">
                        <th class="px-5 py-3 font-medium"></th>
                        <th class="px-5 py-3 font-medium">Nombre</th>
                        <th class="px-5 py-3 font-medium">Razón social</th>
                        <th class="px-5 py-3 font-medium">Punto de venta</th>
                        <th class="px-5 py-3 font-medium">Estado</th>
                        <th class="px-5 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sucursales as $sucursal)
                        <tr wire:key="sucursal-{{ $sucursal->id }}" class="border-b border-gray-50 dark:border-gray-800/60 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                            <td class="px-5 py-3">
                                @if ($sucursal->logo_path)
                                    <img src="{{ $sucursal->logo_url }}" class="w-8 h-8 rounded-md object-contain border border-gray-200 dark:border-gray-800 bg-white">
                                @else
                                    <div class="w-8 h-8 rounded-md border border-dashed border-gray-300 dark:border-gray-700 flex items-center justify-center text-gray-300 dark:text-gray-700">
                                        <x-heroicon-o-building-storefront class="w-4 h-4" />
                                    </div>
                                @endif
                            </td>
                            <td class="px-5 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $sucursal->name }}</td>
                            <td class="px-5 py-3 text-gray-600 dark:text-gray-400">{{ $sucursal->razon_social }}</td>
                            <td class="px-5 py-3 text-gray-500 dark:text-gray-400">{{ str_pad($sucursal->punto_venta, 4, '0', STR_PAD_LEFT) }}</td>
                            <td class="px-5 py-3">
                                @if ($sucursal->active)
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/20 px-2 py-0.5 text-xs font-medium">Activa</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-gray-100 text-gray-600 ring-1 ring-gray-500/20 dark:bg-gray-500/10 dark:text-gray-400 dark:ring-gray-500/20 px-2 py-0.5 text-xs font-medium">Inactiva</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex justify-end gap-2">
                                    <a
                                        href="{{ route('sucursales.edit', $sucursal) }}"
                                        wire:navigate
                                        class="p-1.5 rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200 hover:scale-110 transition-all"
                                    >
                                        <x-heroicon-o-pencil class="w-4 h-4" />
                                    </a>
                                    <button
                                        x-on:click="confirmThen('¿Eliminar la sucursal ' + @js($sucursal->name) + '?', () => $wire.delete({{ $sucursal->id }}))"
                                        class="p-1.5 rounded-md text-gray-500 hover:bg-red-50 hover:text-red-600 dark:text-gray-400 dark:hover:bg-red-500/10 dark:hover:text-red-400 hover:scale-110 transition-all"
                                    >
                                        <x-heroicon-o-trash class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>

            <div class="sm:hidden divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($sucursales as $sucursal)
                    <div wire:key="sucursal-card-{{ $sucursal->id }}" class="p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-start gap-3 min-w-0">
                                @if ($sucursal->logo_path)
                                    <img src="{{ $sucursal->logo_url }}" class="w-8 h-8 rounded-md object-contain border border-gray-200 dark:border-gray-800 bg-white shrink-0">
                                @else
                                    <div class="w-8 h-8 rounded-md border border-dashed border-gray-300 dark:border-gray-700 flex items-center justify-center text-gray-300 dark:text-gray-700 shrink-0">
                                        <x-heroicon-o-building-storefront class="w-4 h-4" />
                                    </div>
                                @endif
                                <div class="min-w-0">
                                    <p class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ $sucursal->name }}</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 truncate">{{ $sucursal->razon_social }}</p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">PV {{ str_pad($sucursal->punto_venta, 4, '0', STR_PAD_LEFT) }} · {{ $sucursal->active ? 'Activa' : 'Inactiva' }}</p>
                                </div>
                            </div>
                            <div class="flex gap-1 shrink-0">
                                <a
                                    href="{{ route('sucursales.edit', $sucursal) }}"
                                    wire:navigate
                                    class="p-1.5 rounded-md text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
                                >
                                    <x-heroicon-o-pencil class="w-4 h-4" />
                                </a>
                                <button
                                    x-on:click="confirmThen('¿Eliminar la sucursal ' + @js($sucursal->name) + '?', () => $wire.delete({{ $sucursal->id }}))"
                                    class="p-1.5 rounded-md text-gray-500 hover:bg-red-50 hover:text-red-600 dark:text-gray-400 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                                >
                                    <x-heroicon-o-trash class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
